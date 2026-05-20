<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Notifications\ProductApproved;
use App\Notifications\ProductRejected;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'categories' => Category::all(),
            'filters' => $request->only(['search', 'category_id', 'status']),
            'pending_count' => Product::where('status', 'pending_review')->count(),
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
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
            'stock' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'media' => ['nullable', 'array', 'max:5'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
        ]);

        $store = Store::findOrFail($data['store_id']);

        DB::transaction(function () use ($data, $request, $store) {
            $product = $store->products()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']) . '-' . Str::random(5),
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'sale_price' => $data['sale_price'] ?? null,
                'stock' => $data['stock'],
                'category_id' => $data['category_id'],
                'status' => 'published',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $index => $file) {
                    $isVideo = str_starts_with($file->getMimeType(), 'video');
                    $folder = 'products/' . $product->id;
                    $path = $file->store($folder, [
                        'disk' => config('filesystems.default'),
                        'visibility' => 'public',
                    ]);

                    $product->media()->create([
                        'type' => $isVideo ? 'video' : 'image',
                        'path' => $path,
                        'url' => Storage::disk(config('filesystems.default'))->url($path),
                        'disk' => config('filesystems.default'),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'order' => $index,
                    ]);
                }
            }
        });

        return redirect()->route('admin.users.show', $store->user)->with('success', 'Product created pour le seller.');
    }

    public function show(Product $product)
    {
        $product->load(['store.user', 'store.city', 'category', 'media', 'orderItems.order']);

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
