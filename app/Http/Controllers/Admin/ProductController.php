<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Notifications\ProductApproved;
use App\Notifications\ProductRejected;
use Auth;
use Illuminate\Http\Request;
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
