<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index() {
        $categories = Category::withCount(['products', 'stores'])
                      ->with('parent')
                      ->latest()
                      ->get();

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'parents'    => Category::whereNull('parent_id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255', 'unique:categories,name'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon'      => ['nullable', 'string', 'max:50'],
        ]);

        Category::create([
            'name'      => $data['name'],
            'slug'      => Str::slug($data['name']),
            'parent_id' => $data['parent_id'] ?? null,
            'icon'      => $data['icon'] ?? null,
        ]);

        return back()->with('success', "Category \"{$data['name']}\" created.");
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255', "unique:categories,name,{$category->id}"],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon'      => ['nullable', 'string', 'max:50'],
        ]);

        // Prevent setting itself as parent
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

        $category->update([
            'name'      => $data['name'],
            'slug'      => Str::slug($data['name']),
            'parent_id' => $data['parent_id'] ?? null,
            'icon'      => $data['icon'] ?? null,
        ]);

        return back()->with('success', "Category updated.");
    }

    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0 || $category->stores()->count() > 0) {
            return back()->withErrors([
                'delete' => "Cannot delete \"{$category->name}\" — it has products or stores attached.",
            ]);
        }

        $category->delete();

        return back()->with('success', "Category deleted.");
    }
}
