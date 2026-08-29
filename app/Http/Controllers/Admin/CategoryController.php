<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount(['products', 'stores'])
            ->with('parent')
            ->latest()
            ->get();

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'parents' => Category::whereNull('parent_id')->get(),
            'locales' => Category::LOCALES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        Category::create([
            'name' => $data['name'],
            // The slug is the URL key, so it is derived once from the French
            // name and never changes per locale.
            'slug' => $this->uniqueSlug($data['name'][Category::FALLBACK_LOCALE]),
            'parent_id' => $data['parent_id'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]);

        return back()->with('success', "Category \"{$data['name'][Category::FALLBACK_LOCALE]}\" created.");
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate($this->rules());

        // Prevent setting itself as parent
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

        // Slug intentionally left untouched — changing it would break existing
        // links to the category.
        $category->update([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]);

        return back()->with('success', 'Category updated.');
    }

    /**
     * Every locale is required so the storefront never falls back to another
     * language mid-page.
     *
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        $rules = [
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'array'],
        ];

        foreach (Category::LOCALES as $locale) {
            $rules["name.{$locale}"] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    private function uniqueSlug(string $from): string
    {
        $base = Str::slug($from);
        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0 || $category->stores()->count() > 0) {
            return back()->withErrors([
                'delete' => "Cannot delete \"{$category->nameFor()}\" — it has products or stores attached.",
            ]);
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
