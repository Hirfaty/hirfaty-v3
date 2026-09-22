<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index()
    {
        return Inertia::render('admin/categories/index', [
            'categories' => $this->categories->listForAdmin(),
            // Only roots are offered as parents: the storefront renders the
            // tree two levels deep, so a third would never be shown.
            'parents' => Category::roots()->get(),
            'locales' => Category::LOCALES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $this->categories->create($data, $request->file('image'));

        return back()->with('success', "Category \"{$data['name'][Category::FALLBACK_LOCALE]}\" created.");
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate($this->rules($category));

        $this->categories->update(
            $category,
            $data,
            $request->file('image'),
            $request->boolean('remove_image'),
        );

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if (! $this->categories->delete($category)) {
            return back()->withErrors([
                'delete' => "Cannot delete \"{$category->nameFor()}\" — it has products or stores attached.",
            ]);
        }

        return back()->with('success', 'Category deleted.');
    }

    /**
     * Every locale is required so the storefront never falls back to another
     * language mid-page.
     *
     * `$category` is the row being edited, if any — it is excluded from its
     * own parent list, since a category cannot sit under itself.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Category $category = null): array
    {
        $rules = [
            // A subcategory can be filed under several crafts at once, so this
            // is a list rather than a single id. An empty one means "root".
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => [
                'integer',
                'exists:categories,id',
                Rule::notIn(array_filter([$category?->id])),
            ],
            'image' => [
                'nullable',
                'image',
                'max:'.config('media.image.max_kb'),
                Rule::dimensions()
                    ->maxWidth(config('media.image.max_width'))
                    ->maxHeight(config('media.image.max_height')),
            ],
            'remove_image' => ['nullable', 'boolean'],
            'name' => ['required', 'array'],
        ];

        foreach (Category::LOCALES as $locale) {
            $rules["name.{$locale}"] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }
}
