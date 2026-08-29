<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Rules\ValidProductMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->store) {
            abort(403, 'Seller store not found.');
        }

        $products = $user
            ->store
            ->products()
            ->with(['category', 'images'])
            ->latest()
            ->paginate(20);

        return Inertia::render('seller/products/index', [
            'products' => $products,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('seller/products/create', [
            'categories' => Category::whereNull('parent_id')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
            'stock' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'media' => ['nullable', 'array', 'max:5'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', new ValidProductMedia],
        ]);

        $user = $request->user();
        if (! $user || ! $user->store) {
            abort(403, 'Seller store not found.');
        }

        DB::transaction(function () use ($data, $request, $user) {
            $store = $user->store;

            $product = $store->products()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::random(5),
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'sale_price' => $data['sale_price'] ?? null,
                'stock' => $data['stock'],
                'category_id' => $data['category_id'],
                'status' => 'pending_review',
            ]);

            $this->uploadMedia($product, $request);
        });

        return redirect()->route('seller.products.index')->with(
            'success', 'Product submitted for review. We\'ll notify you once it\'s approved.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $this->authorizeProduct($product);

        $product->load(['media', 'category']);

        return Inertia::render('seller/products/show', [
            'product' => $product,
            'stats' => [
                'total_views' => $product->views()->count(),
                'total_wishlists' => $product->wishlists()->count(),
                'total_orders' => $product->orderItems()->sum('quantity'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $this->authorizeProduct($product);

        return Inertia::render('seller/products/edit', [
            'product' => $product->load(['images', 'videos', 'category']),
            'categories' => Category::whereNull('parent_id')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $this->authorizeProduct($product);

        // Can only edit if rejected or pending — not published
        if ($product->isPublished()) {
            return back()->withErrors(['status' => 'Published products cannot be edited directly. Archive it first.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
            'stock' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            // delivery options removed — shipping via Amana
            'media' => ['nullable', 'array'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', new ValidProductMedia],
            'deleted_media' => ['nullable', 'array'],
            'deleted_media.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data, $request, $product) {
            $product->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'sale_price' => $data['sale_price'] ?? null,
                'stock' => $data['stock'],
                'category_id' => $data['category_id'],
                'slug' => Str::slug($data['name']).'-'.Str::random(5),
                'status' => 'pending_review', // ← resubmit for review on edit
                'rejection_reason' => null,             // ← clear old rejection
                'reviewed_by' => null,
                'reviewed_at' => null,

            ]);

            // no-op: delivery options table removed

            if (! empty($data['deleted_media'])) {
                $toDelete = $product->media()->whereIn('id', $data['deleted_media'])->get();
                foreach ($toDelete as $media) {
                    Storage::disk($media->disk)->delete($media->path);
                    $media->delete();
                }
            }

            $this->uploadMedia($product, $request);
        });

        return redirect()->route('seller.products.index')
            ->with('success', 'Product resubmitted for review.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->authorizeProduct($product);

        // Delete all media files from storage
        foreach ($product->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $product->delete();

        return redirect()->route('seller.products.index')
            ->with('success', 'Product deleted.');
    }

    // ─── Private helpers ─────────────────────────────────────────

    private function uploadMedia(Product $product, Request $request): void
    {
        if (! $request->hasFile('media')) {
            return;
        }

        $existingCount = $product->images()->count();

        foreach ($request->file('media') as $index => $file) {
            $isVideo = str_starts_with($file->getMimeType(), 'video');
            $folder = 'products/'.$product->id;
            // $path = $file->store($folder, 'public');
            $path = $file->store($folder, [
                'disk' => config('filesystems.default'),
                'visibility' => 'public',
            ]);

            $product->media()->create([
                'type' => $isVideo ? 'video' : 'image',
                'path' => $path,
                // 'url' => Storage::url($path),
                'url' => Storage::disk(config('filesystems.default'))->url($path),
                'disk' => config('filesystems.default'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'order' => $existingCount + $index,
            ]);
        }
    }

    private function authorizeProduct(Product $product): void
    {
        // Make sure this product belongs to the logged-in seller's store
        $user = Auth::user();
        if (! $user || ! $user->store || $product->store_id !== $user->store->id) {
            abort(403);
        }
    }
}
