<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = auth()->user()
                    ->store
                    ->products()
                    ->with(['category', 'images'])
                    ->latest()
                    ->paginate(20);
    
        return Inertia::render('seller/products/index', [
            "pageProps" => $products
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render("seller/products/create", [
            "categories" => Category::whereNull('parent_id')->get()
        ]
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price'  => ['required', 'numeric', 'min:0'],
            'sale_price'  => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
            'stock'       => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'status'      => ['required', 'in:draft,published'],
            'media'       => ['nullable', 'array', 'max:5'],
            'media.*'     => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
        ]);

        


        DB::transaction(function () use ($data, $request) {
            $store = auth()->user()->store;

            $product = $store->products()->create([
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']) . '-' . Str::random(5),
                'description' => $data['description'] ?? null,
                'base_price'  => $data['base_price'],
                'sale_price'  => $data['sale_price'] ?? null,
                'stock'       => $data['stock'],
                'category_id' => $data['category_id'],
                'status'      => $data['status'],
            ]);
            $this->uploadMedia($product, $request);
        });


        return redirect()->route('seller.products.index')->with(
            'success', 'Product created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $this->authorizeProduct($product);

        return Inertia::render('seller/products/edit', [
            'product'    => $product->load(['images', 'videos', 'category']),
            'categories' => Category::whereNull('parent_id')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $this->authorizeProduct($product);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'base_price'    => ['required', 'numeric', 'min:0'],
            'sale_price'    => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
            'stock'         => ['required', 'integer', 'min:0'],
            'category_id'   => ['required', 'exists:categories,id'],
            'status'        => ['required', 'in:draft,published'],
            'media'         => ['nullable', 'array'],
            'media.*'       => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
            'deleted_media' => ['nullable', 'array'],  // IDs of media to remove
            'deleted_media.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data, $request, $product) {
            $product->update([
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']) . '-' . Str::random(5),
                'description' => $data['description'] ?? null,
                'base_price'  => $data['base_price'],
                'sale_price'  => $data['sale_price'] ?? null,
                'stock'       => $data['stock'],
                'category_id' => $data['category_id'],
                'status'      => $data['status'],
            ]);

            // Delete removed media
            if (!empty($data['deleted_media'])) {
                $toDelete = $product->media()->whereIn('id', $data['deleted_media'])->get();
                foreach ($toDelete as $media) {
                    Storage::disk($media->disk)->delete($media->path);
                    $media->delete();
                }
            }

            // Upload new media
            $this->uploadMedia($product, $request);
        });

        return redirect()->route('seller.products.index')
            ->with('success', 'Product updated.');
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
        if (!$request->hasFile('media')) return;

        $existingCount = $product->images()->count();

        foreach ($request->file('media') as $index => $file) {
            $isVideo   = str_starts_with($file->getMimeType(), 'video');
            $folder    = 'products/' . $product->id;
            $path      = $file->store($folder, 'public');

            $product->media()->create([
                'type'      => $isVideo ? 'video' : 'image',
                'path'      => $path,
                'url'       => Storage::url($path),
                'disk'      => 'public',
                'mime_type' => $file->getMimeType(),
                'size'      => $file->getSize(),
                'order'     => $existingCount + $index,
            ]);
        }
    }

    private function authorizeProduct(Product $product): void
    {
        // Make sure this product belongs to the logged-in seller's store
        if ($product->store_id !== auth()->user()->store->id) {
            abort(403);
        }
    }
}
