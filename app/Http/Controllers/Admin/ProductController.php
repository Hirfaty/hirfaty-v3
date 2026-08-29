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
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with(['store', 'category', 'media'])
            ->withCount('orderItems');
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($q) => $q->where('store_name', 'like', "%{$search}%"));
            });
        }

        if ($category = $request->input('category_id')) {
            $query->where('category_id', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $products = $query->latest()->paginate(20)->withQueryString();

        // Status tab counts must ignore the active status filter (so a tab
        // never reports 0 just because another tab is selected) but still
        // respect search/category, matching the scope the admin is looking at.
        $countsQuery = Product::query();
        if ($search) {
            $countsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($q) => $q->where('store_name', 'like', "%{$search}%"));
            });
        }
        if ($category) {
            $countsQuery->where('category_id', $category);
        }
        $statusCounts = (clone $countsQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'categories' => Category::all(),
            'filters' => $request->only(['search', 'category_id', 'status']),
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

    public function create(Request $request)
    {
        $store = null;
        $storeId = $request->query('store_id');

        if ($storeId) {
            $store = Store::with('user')->find($storeId);
        }

        return Inertia::render('admin/products/create', [
            'categories' => Category::whereNull('parent_id')->get(),
            'store' => $store,
            // The admin can file a product under any seller, so offer them all
            // when no store was pre-selected via ?store_id=.
            'stores' => $store ? null : Store::with('user')->orderBy('store_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $isDraft = $request->input('status') === 'draft';
        $data = $request->validate($this->productRules($isDraft));

        $store = Store::findOrFail($data['store_id']);

        $product = DB::transaction(function () use ($data, $request, $store, $isDraft) {
            $hasVariants = ! empty($data['variants']);

            $product = $store->products()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::random(5),
                // Blank strings become null so the unique index stays usable.
                'sku' => ($data['sku'] ?? null) ?: null,
                'description' => $data['description'] ?? null,

                // Retail price list. A draft may not have a price yet.
                'base_price' => $data['base_price'] ?? 0,
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
            ]);

            $this->syncVariants($product, $data['variants'] ?? [], $request);
            $this->uploadMedia($product, $request);

            return $product;
        });

        return redirect()
            ->route('admin.products.edit', $product)
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
            'categories' => Category::whereNull('parent_id')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $isDraft = $request->input('status') === 'draft';
        $data = $request->validate($this->productRules($isDraft, $product));

        DB::transaction(function () use ($data, $request, $product, $isDraft) {
            $hasVariants = ! empty($data['variants']);

            $product->update([
                'name' => $data['name'],
                // Blank strings become null so the unique index stays usable.
                'sku' => ($data['sku'] ?? null) ?: null,
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'] ?? 0,
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

            // Retail pricing.
            'base_price' => [$required, 'numeric', 'min:0'],
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
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.stock' => [$required, 'integer', 'min:0'],
            'variants.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.professional_price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.options' => ['required', 'array', 'min:1'],
            'variants.*.options.*.type' => ['required', Rule::in(ProductVariantOption::TYPES)],
            'variants.*.options.*.name' => ['required', 'string', 'max:50'],
            // A colour/size option is identified by its label, so the value is
            // optional there and defaults to the name.
            'variants.*.options.*.value' => ['nullable', 'string', 'max:50'],
            'variants.*.options.*.color_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // Per-variant photos — images only (a colour/size swatch photo
            // has no need for video).
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
        ];
    }

    /**
     * Sync a product's variants to the supplied set, matched by position.
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
        $existing = $product->variants()->orderBy('position')->get();

        foreach (array_values($variants) as $position => $variant) {
            $attributes = [
                'sku' => $variant['sku'] ?? null,
                'stock' => $variant['stock'],
                'price_override' => $variant['price_override'] ?? null,
                'professional_price_override' => $variant['professional_price_override'] ?? null,
                'position' => $position,
                'is_active' => true,
            ];

            /** @var ProductVariant|null $row */
            $row = $existing->get($position);

            if ($row) {
                $row->update($attributes);
                $row->options()->delete();
            } else {
                $row = $product->variants()->create($attributes);
            }

            $row->options()->createMany(
                collect($variant['options'])
                    ->map(fn (array $o) => [
                        'type' => $o['type'],
                        'name' => $o['name'],
                        // Colour and size are identified by their label, so the
                        // value mirrors the name when none was supplied.
                        'value' => $o['value'] ?? $o['name'],
                        'color_hex' => $o['type'] === 'color' ? ($o['color_hex'] ?? null) : null,
                    ])
                    ->all()
            );

            $this->deleteVariantMedia($row, $variant['deleted_media'] ?? []);
            $this->uploadVariantMedia($row, $request, $position);
        }

        // Any existing variant beyond the new count (the admin removed a
        // slot) is gone — clean up its media so nothing leaks in storage.
        $existing->slice(count($variants))->each(function (ProductVariant $v) {
            $this->deleteVariantMedia($v, $v->media()->pluck('id')->all());
            $v->delete();
        });
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

    public function show(Product $product)
    {
        $product->load(['store.user', 'store.city', 'category', 'media', 'orderItems.order', 'variants.options', 'variants.media']);

        return Inertia::render('admin/products/show', [
            'product' => $product,
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
}
