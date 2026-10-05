<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Notifications\ProductApproved;
use App\Notifications\ProductRejected;
use App\Rules\ValidProductMedia;
use App\Services\AdminProductListService;
use App\Services\ProductBulkStatusService;
use App\Services\ProductDeletionService;
use App\Services\ProductShippingService;
use App\Services\ReviewService;
use App\Services\SpreadsheetExportService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private readonly ProductShippingService $shipping) {}

    public function index(Request $request, AdminProductListService $list)
    {
        $filters = $request->only(['search', 'category_id', 'status']);

        $products = $list->query($filters)
            ->with(['store', 'category', 'media'])
            ->withCount('orderItems')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Status tab counts must ignore the active status filter (so a tab
        // never reports 0 just because another tab is selected) but still
        // respect search/category, matching the scope the admin is looking at.
        $countsQuery = $list->query([...$filters, 'status' => null]);
        $statusCounts = (clone $countsQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'categories' => Category::all(),
            'filters' => $filters,
            'totalProducts' => (clone $countsQuery)->count(),
            'statusCounts' => [
                'draft' => $statusCounts->get('draft', 0),
                'pending_review' => $statusCounts->get('pending_review', 0),
                'published' => $statusCounts->get('published', 0),
                'rejected' => $statusCounts->get('rejected', 0),
                'archived' => $statusCounts->get('archived', 0),
            ],
        ]);
    }

    /**
     * Download the products table — every page of it, under the filters the
     * admin currently has applied — as CSV or Excel.
     */
    public function export(Request $request, AdminProductListService $list)
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(SpreadsheetExportService::FORMATS)],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['draft', 'pending_review', 'published', 'rejected', 'archived'])],
        ]);

        return $list->export($data['format'], $data);
    }

    public function create(Request $request)
    {
        $store = null;
        $storeId = $request->query('store_id');

        if ($storeId) {
            $store = Store::with('user')->find($storeId);
        }

        return Inertia::render('admin/products/create', [
            // Full tree, not just the roots: the form offers a subcategory
            // picker under whichever main category is selected, mirroring
            // the buyer-facing filter rail (Buyer\ProductController).
            'categories' => Category::roots()
                ->orderBy('id')
                ->with(['children' => fn ($query) => $query->orderBy('categories.id')])
                ->get(),
            'store' => $store,
            // The admin can file a product under any seller, so offer them all
            // when no store was pre-selected via ?store_id=.
            'stores' => $store ? null : Store::with('user')->orderBy('store_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $isDraft = $request->input('status') === 'draft';
        $data = $request->validate($this->productRules($isDraft), $this->productMessages());
        $this->assertVariantsAreCoherent($data['variants'] ?? []);

        $store = Store::findOrFail($data['store_id']);

        $product = DB::transaction(function () use ($data, $request, $store, $isDraft) {
            $hasVariants = ! empty($data['variants']);

            $product = $store->products()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::random(5),
                // Blank strings become null so the unique index stays usable.
                'sku' => ($data['sku'] ?? null) ?: null,
                'description' => $data['description'] ?? null,

                // Retail price list. NULL means "not priced yet", which is not
                // the same as 0 (free) — an unpriced product stays off the
                // storefront until a figure is agreed, rather than being
                // published at nothing.
                'base_price' => $data['base_price'] ?? null,
                'sale_price' => $data['sale_price'] ?? null,

                // Professional price list — optional; when absent the product is
                // simply sold to professionals at the retail price.
                'professional_price' => $data['professional_price'] ?? null,
                'professional_sale_price' => $data['professional_sale_price'] ?? null,
                'min_order_qty_professional' => $data['min_order_qty_professional'] ?? 1,

                // With variants, stock is tracked per variant instead.
                'stock' => $hasVariants ? 0 : ($data['stock'] ?? 0),
                'has_variants' => $hasVariants,

                'visibility' => $data['visibility'] ?? 'both',
                'category_id' => $data['category_id'] ?? null,
                'status' => $isDraft ? 'draft' : 'published',
                // A draft has not been reviewed yet, so don't stamp a reviewer.
                'reviewed_by' => $isDraft ? null : Auth::id(),
                'reviewed_at' => $isDraft ? null : now(),
                ...$this->shipping->productAttributes($data),
            ]);

            $this->syncVariants($product, $data['variants'] ?? [], $request);
            $this->uploadMedia($product, $request);

            return $product;
        });

        return redirect()
            ->route('admin.products.show', $product)
            ->with(
                'success',
                $isDraft
                    ? "Brouillon « {$product->name} » enregistré."
                    : "Produit « {$product->name} » publié pour {$store->store_name}."
            );
    }

    public function edit(Product $product)
    {
        $product->load(['store.user', 'category', 'media', 'variants.options', 'variants.media']);

        return Inertia::render('admin/products/edit', [
            'product' => $product,
            'categories' => Category::roots()
                ->orderBy('id')
                ->with(['children' => fn ($query) => $query->orderBy('categories.id')])
                ->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $isDraft = $request->input('status') === 'draft';
        $data = $request->validate($this->productRules($isDraft, $product), $this->productMessages());
        $this->assertVariantsAreCoherent($data['variants'] ?? []);

        DB::transaction(function () use ($data, $request, $product, $isDraft) {
            $hasVariants = ! empty($data['variants']);

            $product->update([
                'name' => $data['name'],
                // Blank strings become null so the unique index stays usable.
                'sku' => ($data['sku'] ?? null) ?: null,
                'description' => $data['description'] ?? null,
                // See store(): NULL is "not priced yet", not free.
                'base_price' => $data['base_price'] ?? null,
                'sale_price' => $data['sale_price'] ?? null,
                'professional_price' => $data['professional_price'] ?? null,
                'professional_sale_price' => $data['professional_sale_price'] ?? null,
                'min_order_qty_professional' => $data['min_order_qty_professional'] ?? 1,
                'stock' => $hasVariants ? 0 : ($data['stock'] ?? 0),
                'has_variants' => $hasVariants,
                'visibility' => $data['visibility'] ?? 'both',
                'category_id' => $data['category_id'] ?? null,
                'status' => $isDraft ? 'draft' : 'published',
                'reviewed_by' => $isDraft ? null : Auth::id(),
                'reviewed_at' => $isDraft ? null : now(),
                ...$this->shipping->productAttributes($data),
            ]);

            $this->syncVariants($product, $data['variants'] ?? [], $request);

            $this->deleteMedia($product, $data['deleted_media'] ?? []);
            $this->uploadMedia($product, $request);
            $this->applyMediaOrder($product, $data['media_order'] ?? []);
        });

        return redirect()
            ->route('admin.products.edit', $product)
            ->with(
                'success',
                $isDraft ? 'Brouillon mis à jour.' : 'Produit publié.'
            );
    }

    /**
     * Validation rules.
     *
     * Drafts deliberately relax almost everything so the admin can save a
     * half-filled product and come back to it; only a name is required to have
     * something to find it by. Publishing enforces the full set.
     *
     * @return array<string, mixed>
     */
    private function productRules(bool $isDraft = false, ?Product $product = null): array
    {
        $required = $isDraft ? 'nullable' : 'required';

        // Ignore this product's own SKU so re-saving doesn't collide with itself.
        $skuUnique = Rule::unique('products', 'sku')->ignore($product?->id);

        return [
            // The seller is always needed — a product must belong to a store.
            'store_id' => [$product ? 'nullable' : 'required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', $skuUnique],
            'description' => ['nullable', 'string'],
            'category_id' => [$required, 'exists:categories,id'],
            'status' => ['nullable', 'in:draft,published'],

            /*
             * Retail pricing. `base_price` is optional even when publishing:
             * the admin catalogues an artisan's work before the price has
             * been agreed, and inventing a 0 would mean "free", not
             * "unpriced". An unpriced product is kept out of the storefront
             * by Product::scopeStorefront() until a figure is filled in.
             */
            // A discount off a price that doesn't exist yet is meaningless,
            // and `lt:base_price` silently passes when base_price is absent —
            // so a sale price drags the base price back to being required.
            'base_price' => ['nullable', 'required_with:sale_price', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],

            // Professional pricing.
            'professional_price' => ['nullable', 'numeric', 'min:0'],
            'professional_sale_price' => ['nullable', 'numeric', 'min:0', 'lt:professional_price'],
            'min_order_qty_professional' => ['nullable', 'integer', 'min:1'],

            'visibility' => ['nullable', 'in:individual,professional,both'],

            // Required only when the product has no variants — otherwise stock
            // lives on each variant.
            'stock' => [$isDraft ? 'nullable' : 'required_without:variants', 'nullable', 'integer', 'min:0'],

            'variants' => ['nullable', 'array'],
            /*
             * The id of an already-saved variant, so it can be matched on save
             * instead of by array position. Scoped to this product: without
             * the scope a crafted request could rebind another product's
             * variant — and cart_items/order_items point at these ids.
             */
            'variants.*.id' => [
                'nullable',
                'integer',
                $product
                    ? Rule::exists('product_variants', 'id')->where('product_id', $product->id)
                    : 'prohibited',
            ],
            /*
             * Checked here so a clash comes back as a form error rather than a
             * 500 from the unique index. This product's own variants are left
             * out: syncVariants() frees their SKUs before reassigning them, so
             * moving a reference from one variant to another — or removing a
             * variant and re-adding one with the same reference — is fine.
             */
            'variants.*.sku' => [
                'nullable',
                'string',
                'max:100',
                'distinct',
                Rule::unique('product_variants', 'sku')->where(
                    fn ($query) => $product ? $query->where('product_id', '!=', $product->id) : $query
                ),
            ],
            'variants.*.stock' => [$required, 'integer', 'min:0'],
            'variants.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.professional_price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.options' => ['required', 'array', 'min:1'],
            'variants.*.options.*.type' => ['required', Rule::in(ProductVariantOption::TYPES)],
            // The axis ("Couleur"), not the value. Canonical for every type
            // but `other`, which the seller names — see normaliseOptions().
            'variants.*.options.*.name' => ['nullable', 'string', 'max:50'],
            // The point on that axis ("Rouge"). Required: it is what the
            // shopper actually picks.
            'variants.*.options.*.value' => ['required', 'string', 'max:50'],

            // Per-variant photos — images only (a variant photo has no need
            // for video).
            'variants.*.media' => ['nullable', 'array', 'max:5'],
            'variants.*.media.*' => ['file', 'mimes:jpg,jpeg,png,webp', new ValidProductMedia],
            // Existing variant media ids to remove (edit screen only).
            'variants.*.deleted_media' => ['nullable', 'array'],
            'variants.*.deleted_media.*' => ['integer'],

            'media' => ['nullable', 'array', 'max:10'],
            // `mimes` sniffs the real file type; ValidProductMedia layers
            // per-type size/dimension limits on top (see config/media.php).
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', new ValidProductMedia],

            // Existing media ids to remove / reorder (edit screen only).
            'deleted_media' => ['nullable', 'array'],
            'deleted_media.*' => ['integer'],
            'media_order' => ['nullable', 'array'],
            'media_order.*' => ['integer'],

            // Weight, packed dimensions, customs facts — product and variants.
            ...$this->shipping->rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function productMessages(): array
    {
        return [
            'variants.*.sku.distinct' => 'Cette référence est utilisée par une autre déclinaison du formulaire.',
            'variants.*.sku.unique' => 'Cette référence est déjà utilisée par un autre produit.',
        ];
    }

    /**
     * Sync a product's variants to the supplied set, matched by id.
     *
     * Deliberately an upsert rather than delete-then-recreate: a variant's
     * photos live on the variant row via a polymorphic Media relation, and
     * destroying/recreating the row on every save would orphan those files
     * in storage and force the admin to re-upload them on every unrelated
     * edit (e.g. just bumping stock). Options are cheap to fully replace —
     * there is no media hanging off them.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants, Request $request): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $keptIds = collect($variants)->pluck('id')->filter()->all();

        /*
         * Clear the way before writing anything, because the SKU index is
         * checked row by row. The variants the admin removed go first, and the
         * kept ones give up their SKUs for the moment — otherwise re-adding a
         * removed variant's reference, or swapping two references, would hit
         * a row that is about to lose it anyway. Each kept row gets its SKU
         * back in the loop below.
         */
        $existing->except($keptIds)->each(function (ProductVariant $v) {
            $this->deleteVariantMedia($v, $v->media()->pluck('id')->all());
            $v->delete();
        });

        if ($keptIds !== []) {
            $product->variants()->whereKey($keptIds)->whereNotNull('sku')->update(['sku' => null]);

            // Reloaded so the models know their SKU is now null — otherwise an
            // unchanged reference looks clean to update() and is never written
            // back.
            $existing = $product->variants()->whereKey($keptIds)->get()->keyBy('id');
        }

        foreach (array_values($variants) as $position => $variant) {
            $attributes = [
                'sku' => ($variant['sku'] ?? null) ?: null,
                'stock' => $variant['stock'],
                'price_override' => $variant['price_override'] ?? null,
                'professional_price_override' => $variant['professional_price_override'] ?? null,
                ...$this->shipping->variantAttributes($variant),
                'position' => $position,
                'is_active' => (bool) ($variant['is_active'] ?? true),
            ];

            /*
             * Matched by id, never by array position.
             *
             * Position matching silently rebound rows: deleting the first of
             * three variants and saving made variant #1 take on #2's options
             * and #3 disappear. Since cart_items and order_items hold
             * product_variant_id, a shopper's basket line would quietly become
             * a different colour/size than the one they chose.
             */
            $row = isset($variant['id']) ? $existing->get($variant['id']) : null;

            if ($row) {
                $row->update($attributes);
                $row->options()->delete();
            } else {
                $row = $product->variants()->create($attributes);
            }

            $row->options()->createMany($this->normaliseOptions($variant['options']));

            $this->deleteVariantMedia($row, $variant['deleted_media'] ?? []);
            $this->uploadVariantMedia($row, $request, $position);
        }
    }

    /**
     * Turn submitted options into rows, forcing the canonical axis name for
     * every type but `other`.
     *
     * The axis is derived server-side rather than trusted from the form, so
     * "Couleur" cannot arrive as "couleur"/"Color" and split one selector row
     * into several that no variant can satisfy together.
     *
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, string>>
     */
    private function normaliseOptions(array $options): array
    {
        return collect($options)
            ->map(fn (array $o) => [
                'type' => $o['type'],
                'name' => ProductVariantOption::axisFor($o['type'])
                    ?? trim((string) ($o['name'] ?? '')) ?: 'Option',
                'value' => trim((string) $o['value']),
            ])
            ->all();
    }

    /**
     * Structural rules a set of variants must satisfy, checked before anything
     * is written.
     *
     * Both failures here are silent-corruption bugs rather than cosmetic ones:
     *
     *  - Ragged axes (one variant on "Couleur", another on "Dimension") make
     *    the storefront render selector rows that no single variant answers,
     *    so the product cannot be added to a basket at all. This is the shape
     *    the old admin produced.
     *  - Two variants on the same combination leave the shopper picking one
     *    row while stock silently splits across two, and the storefront can
     *    only ever resolve the first.
     *
     * @param  array<int, array<string, mixed>>  $variants
     *
     * @throws ValidationException
     */
    private function assertVariantsAreCoherent(array $variants): void
    {
        if ($variants === []) {
            return;
        }

        $axesOf = function (array $variant): array {
            $axes = collect($this->normaliseOptions($variant['options']))
                ->pluck('name')
                ->all();
            sort($axes);

            return $axes;
        };

        $expected = $axesOf($variants[array_key_first($variants)]);
        $seen = [];

        foreach (array_values($variants) as $i => $variant) {
            if ($axesOf($variant) !== $expected) {
                throw ValidationException::withMessages([
                    "variants.{$i}.options" => 'Toutes les déclinaisons doivent utiliser les mêmes options ('
                        .implode(', ', $expected).').',
                ]);
            }

            // Order-independent, so Rouge/4m and 4m/Rouge count as the same.
            $signature = collect($this->normaliseOptions($variant['options']))
                ->map(fn (array $o) => $o['name'].'='.mb_strtolower($o['value']))
                ->sort()
                ->implode('|');

            if (isset($seen[$signature])) {
                throw ValidationException::withMessages([
                    "variants.{$i}.options" => 'Cette combinaison existe déjà dans une autre déclinaison.',
                ]);
            }

            $seen[$signature] = true;
        }
    }

    private function uploadVariantMedia(ProductVariant $variant, Request $request, int $position): void
    {
        $files = $request->file("variants.{$position}.media");

        if (! $files) {
            return;
        }

        $disk = config('filesystems.default');
        $highest = $variant->media()->max('order');
        $offset = $highest === null ? 0 : (int) $highest + 1;

        foreach (array_values($files) as $index => $file) {
            $path = $file->store("products/{$variant->product_id}/variants/{$variant->id}", [
                'disk' => $disk,
                'visibility' => 'public',
            ]);

            $variant->media()->create([
                'type' => 'image',
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
                'disk' => $disk,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'order' => $offset + $index,
            ]);
        }
    }

    /**
     * Detach variant media and remove the underlying files.
     *
     * @param  array<int, int>  $ids
     */
    private function deleteVariantMedia(ProductVariant $variant, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        foreach ($variant->media()->whereIn('id', $ids)->get() as $media) {
            try {
                Storage::disk($media->disk)->delete($media->path);
            } catch (\Throwable $e) {
                Log::warning("Could not delete variant media file {$media->path}: ".$e->getMessage());
            }

            $media->delete();
        }
    }

    private function uploadMedia(Product $product, Request $request): void
    {
        if (! $request->hasFile('media')) {
            return;
        }

        $disk = config('filesystems.default');
        // Append after whatever is already attached (edit screen). With an empty
        // gallery max() is null, and the first upload must land at order 0.
        $highest = $product->media()->max('order');
        $offset = $highest === null ? 0 : (int) $highest + 1;

        foreach (array_values($request->file('media')) as $index => $file) {
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video');
            $path = $file->store("products/{$product->id}", [
                'disk' => $disk,
                'visibility' => 'public',
            ]);

            $product->media()->create([
                'type' => $isVideo ? 'video' : 'image',
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
                'disk' => $disk,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'order' => $offset + $index,
            ]);
        }
    }

    /**
     * Detach media and remove the underlying files.
     *
     * @param  array<int, int>  $ids
     */
    private function deleteMedia(Product $product, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        foreach ($product->media()->whereIn('id', $ids)->get() as $media) {
            // Guard the file delete: a missing object must not abort the request.
            try {
                Storage::disk($media->disk)->delete($media->path);
            } catch (\Throwable $e) {
                Log::warning("Could not delete media file {$media->path}: ".$e->getMessage());
            }

            $media->delete();
        }
    }

    /**
     * Persist the gallery order the admin dragged into place.
     *
     * @param  array<int, int>  $orderedIds
     */
    private function applyMediaOrder(Product $product, array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        // Only reorder media that actually belongs to this product.
        $owned = $product->media()->pluck('id')->all();

        foreach (array_values($orderedIds) as $position => $id) {
            if (in_array((int) $id, $owned, true)) {
                $product->media()->whereKey($id)->update(['order' => $position]);
            }
        }
    }

    public function show(Product $product, ReviewService $reviews)
    {
        $product->load(['store.user', 'store.city', 'category', 'media', 'orderItems.order', 'variants.options', 'variants.media']);

        return Inertia::render('admin/products/show', [
            'product' => $product,
            'reviews' => $reviews->forAdmin($product),
            'shipping' => [
                'complete' => $this->shipping->isComplete($product),
                'hs_code' => $this->shipping->hsCode($product),
                'hs_code_inherited' => $product->hs_code === null,
            ],
        ]);
    }

    public function approve(Product $product)
    {
        $product->update([
            'status' => 'published',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        try {
            $product->store->user->notify(new ProductApproved($product));
        } catch (\Exception $e) {
            \Log::warning('Failed to notify seller for product approval: '.$e->getMessage());
        }

        return back()->with('success', "Product \"{$product->name}\" approved and published.");
    }

    public function reject(Request $request, Product $product)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $product->update([
            'status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        try {
            $product->store->user->notify(new ProductRejected($product));
        } catch (\Exception $e) {
            \Log::warning('Failed to notify seller for product rejection: '.$e->getMessage());
        }

        return back()->with('success', "Product \"{$product->name}\" rejected.");
    }

    public function archive(Product $product)
    {
        $product->update(['status' => 'archived']);

        return back()->with('success', 'Product archived.');
    }

    public function destroy(Product $product, ProductDeletionService $deletions)
    {
        $name = $product->name;

        /*
         * Captured before the row goes: once it is deleted these routes can no
         * longer be built from the model.
         */
        $ownScreens = [
            route('admin.products.show', $product),
            route('admin.products.edit', $product),
        ];

        try {
            $deletions->delete($product);
        } catch (\RuntimeException $e) {
            // A flash message, not a validation error: there's no form field
            // for "delete" to attach to, and withErrors() would land in the
            // errors bag where nothing on this page ever looks for it.
            return back()->with('error', $e->getMessage());
        }

        /*
         * Deleting a product from its own screen leaves nowhere to return to,
         * so that case lands on the catalogue. Every other caller — the
         * catalogue itself, and the products table on a seller's profile —
         * goes back where it was, keeping the admin's place (and any filters
         * in the URL) instead of being thrown to a different page.
         */
        if (Str::startsWith(url()->previous(), $ownScreens)) {
            return redirect()
                ->route('admin.products.index')
                ->with('success', "Product \"{$name}\" deleted.");
        }

        return back()->with('success', "Product \"{$name}\" deleted.");
    }

    public function bulkStatus(Request $request, ProductBulkStatusService $statuses)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(ProductBulkStatusService::TRANSITIONS))],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        try {
            $count = $statuses->apply($data['action'], $data['ids']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $verb = $data['action'] === 'publish' ? 'published' : 'archived';

        return back()->with('success', $count.' product'.($count === 1 ? '' : 's').' '.$verb.'.');
    }

    public function bulkDestroy(Request $request, ProductDeletionService $deletions)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
            // Wiping many listings at once is one misclick away from losing
            // a catalogue, so the admin re-types their password to confirm.
            'password' => ['required', 'string', 'current_password'],
        ]);

        $result = $deletions->deleteMany($data['ids']);

        $message = $result['deleted'].' product'.($result['deleted'] === 1 ? '' : 's').' deleted.';

        if ($result['blocked'] !== []) {
            $message .= ' Skipped (has order history): '.implode(', ', $result['blocked']).'.';
        }

        return back()->with('success', $message);
    }
}
