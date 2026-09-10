<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Models\Blog;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BlogController extends Controller
{
    public function __construct(private BlogService $blogs) {}

    public function index(Request $request)
    {
        $query = Blog::query()->with(['author', 'media']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Newest first by the date the reader sees, falling back to creation
        // for drafts, which have no published_at yet.
        $blogs = $query
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate(15)
            ->withQueryString();

        // Tab counts ignore the active status filter — otherwise every tab but
        // the selected one would read 0 — while still respecting the search.
        $counts = Blog::query()
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('admin/blogs/index', [
            // through() rather than BlogResource::collection(): it maps the
            // rows while leaving the paginator's own keys at the top level,
            // which is the shape the frontend's PaginatedData<T> expects.
            'blogs' => $blogs->through(fn ($blog) => (new BlogResource($blog))->resolve()),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
            ],
            'statusCounts' => $counts,
            'statuses' => Blog::STATUSES,
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/blogs/create', [
            'statuses' => Blog::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $blog = $this->blogs->create(
            $data,
            $request->user(),
            $request->file('cover'),
        );

        return redirect()
            ->route('admin.blogs.edit', $blog)
            ->with('success', "Article « {$blog->title} » créé.");
    }

    public function edit(Blog $blog)
    {
        $blog->load(['author', 'media']);

        return Inertia::render('admin/blogs/edit', [
            'blog' => (new BlogResource($blog))->withContent(),
            'statuses' => Blog::STATUSES,
        ]);
    }

    public function update(Request $request, Blog $blog)
    {
        $data = $request->validate($this->rules());

        $this->blogs->update(
            $blog,
            $data,
            $request->file('cover'),
            $request->boolean('remove_cover'),
        );

        return back()->with('success', 'Article enregistré.');
    }

    /**
     * The article exactly as a visitor would see it, drafts included — the
     * whole point being to check the layout before anyone else can.
     *
     * Renders the same page component as the public route, so what is approved
     * here is what ships.
     */
    public function preview(Blog $blog)
    {
        $blog->load(['author', 'media']);

        return Inertia::render('blog/show', [
            'blog' => (new BlogResource($blog))->withContent(),
            'related' => BlogResource::collection(
                Blog::published()->whereKeyNot($blog->id)->latest('published_at')->take(3)->get()
            ),
            // Drives the "you are previewing a draft" banner and the back link.
            'preview' => true,
        ]);
    }

    public function destroy(Blog $blog)
    {
        $title = $blog->title;
        $this->blogs->delete($blog);

        return redirect()
            ->route('admin.blogs.index')
            ->with('success', "Article « {$title} » supprimé.");
    }

    /**
     * Receives an image dropped into the editor body and answers with its URL.
     *
     * XHR rather than part of the form submit: the editor needs a real src to
     * put in the document the moment the image lands, long before the article
     * itself is saved.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
        ]);

        return response()->json([
            'url' => $this->blogs->storeInlineImage($request->file('image')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:200000'],
            'status' => ['required', Rule::in(Blog::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }
}
