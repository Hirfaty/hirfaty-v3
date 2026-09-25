<?php

namespace App\Http\Controllers;

use App\Http\Resources\BlogResource;
use App\Models\Blog;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The public-facing journal. Unauthenticated, like the product listing.
 */
class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Blog::published()->with(['author', 'media']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $blogs = $query->latest('published_at')->paginate(9)->withQueryString();

        return Inertia::render('blog/index', [
            // See Admin\BlogController::index — through() keeps the paginator's
            // own keys flat, matching PaginatedData<T> on the frontend.
            'blogs' => $blogs->through(fn ($blog) => (new BlogResource($blog))->resolve()),
            'filters' => ['search' => $search ?? ''],
        ]);
    }

    public function show(string $slug, SeoService $seo)
    {
        // Scoped to published rather than resolved by route-model binding: a
        // draft's slug is guessable, and a 404 must not leak that it exists.
        $blog = Blog::published()->with(['author', 'media'])->where('slug', $slug)->firstOrFail();

        return Inertia::render('blog/show', [
            'blog' => (new BlogResource($blog))->withContent(),
            'related' => BlogResource::collection(
                Blog::published()
                    ->with('media')
                    ->whereKeyNot($blog->id)
                    ->latest('published_at')
                    ->take(3)
                    ->get()
            ),
            'preview' => false,
        ])->withViewData('seo', fn () => $seo->forBlog($blog));
    }
}
