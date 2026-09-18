<?php

namespace App\Http\Resources;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Blog
 */
class BlogResource extends JsonResource
{
    /**
     * Whether to ship the full article body.
     *
     * Off by default: the listing screens send dozens of rows at once, and
     * inlining every post's HTML there would dwarf the rest of the page. The
     * screens that actually render an article call withContent().
     */
    private bool $withContent = false;

    public function withContent(): static
    {
        $this->withContent = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,

            // Sanitised on write by HtmlSanitizer, so the pages that render it
            // with dangerouslySetInnerHTML are rendering an allowlisted subset.
            $this->mergeWhen($this->withContent, fn () => [
                'content' => $this->content,
            ]),

            'status' => $this->status,
            'is_published' => $this->isPublished(),
            // Drives the row's "Publier maintenant" action: true only while the
            // post is published-but-future, which is the one case where an
            // admin may want to release it ahead of its own date.
            'is_scheduled' => $this->isScheduled(),
            'published_at' => $this->published_at?->toIso8601String(),
            'cover_url' => $this->coverUrl(),
            'reading_minutes' => $this->readingMinutes(),
            'author' => $this->whenLoaded(
                'author',
                fn () => $this->author === null ? null : [
                    'id' => $this->author->id,
                    'name' => trim("{$this->author->first_name} {$this->author->last_name}"),
                ],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
