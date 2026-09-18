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

        $this->categories->create($data, $request->file('image'));

        return back()->with('success', "Category \"{$data['name'][Category::FALLBACK_LOCALE]}\" created.");
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate($this->rules());

        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

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
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        $rules = [
            'parent_id' => ['nullable', 'exists:categories,id'],
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
