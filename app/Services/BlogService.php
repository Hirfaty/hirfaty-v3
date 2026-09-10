<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything that writing an article touches: slugging, HTML sanitising, the
 * publish stamp and the cover image.
 *
 * Kept out of the controller because the create and update paths must agree on
 * all four — a post whose slug was generated one way on create and another on
 * update would quietly break its own public URL.
 */
class BlogService
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    /**
     * @param  array{title: string, excerpt?: ?string, content?: ?string, status: string, published_at?: ?string}  $data
     */
    public function create(array $data, User $author, ?UploadedFile $cover = null): Blog
    {
        return DB::transaction(function () use ($data, $author, $cover) {
            $blog = Blog::create([
                'user_id' => $author->id,
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title']),
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $this->sanitizer->clean($data['content'] ?? null),
                'status' => $data['status'],
                'published_at' => $this->resolvePublishedAt($data, null),
            ]);

            if ($cover !== null) {
                $this->replaceCover($blog, $cover);
            }

            return $blog;
        });
    }

    /**
     * @param  array{title: string, excerpt?: ?string, content?: ?string, status: string, published_at?: ?string}  $data
     */
    public function update(Blog $blog, array $data, ?UploadedFile $cover = null, bool $removeCover = false): Blog
    {
        return DB::transaction(function () use ($blog, $data, $cover, $removeCover) {
            $blog->update([
                'title' => $data['title'],
                // The slug is the article's public URL, so it is only rebuilt
                // while the post has never been live. Renaming a published
                // post's title must not 404 every link already shared.
                'slug' => $blog->published_at === null
                    ? $this->uniqueSlug($data['title'], $blog->id)
                    : $blog->slug,
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $this->sanitizer->clean($data['content'] ?? null),
                'status' => $data['status'],
                'published_at' => $this->resolvePublishedAt($data, $blog),
            ]);

            if ($removeCover && $cover === null) {
                $this->deleteMedia($blog);
            }

            if ($cover !== null) {
                $this->replaceCover($blog, $cover);
            }

            return $blog->refresh();
        });
    }

    public function delete(Blog $blog): void
    {
        DB::transaction(function () use ($blog) {
            $this->deleteMedia($blog);
            $blog->delete();
        });
    }

    /**
     * Store an image dropped into the editor body and hand back its public URL.
     *
     * These are deliberately not Media rows: the editor uploads them while the
     * article may not exist yet (the create screen), and the <img> tag in the
     * saved HTML is the only thing that references them.
     */
    public function storeInlineImage(UploadedFile $file): string
    {
        $disk = config('filesystems.default');

        $path = $file->store('blogs/inline', [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        return Storage::disk($disk)->url($path);
    }

    /**
     * A post is stamped the moment it first goes public and keeps that date
     * afterwards, so un-publishing and re-publishing does not reshuffle the
     * listing. An explicit date from the form always wins — that is how an
     * admin schedules a post or backdates an import.
     *
     * @param  array{published_at?: ?string, status: string}  $data
     */
    private function resolvePublishedAt(array $data, ?Blog $blog): ?string
    {
        if (! empty($data['published_at'])) {
            return $data['published_at'];
        }

        if ($blog?->published_at !== null) {
            return $blog->published_at->toDateTimeString();
        }

        return $data['status'] === Blog::STATUS_PUBLISHED
            ? now()->toDateTimeString()
            : null;
    }

    private function replaceCover(Blog $blog, UploadedFile $file): void
    {
        $this->deleteMedia($blog);

        $disk = config('filesystems.default');
        $path = $file->store("blogs/{$blog->id}", [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        $blog->media()->create([
            'type' => 'image',
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'order' => 0,
        ]);
    }

    /** Detach the cover rows and remove the files behind them. */
    private function deleteMedia(Blog $blog): void
    {
        foreach ($blog->media()->get() as $media) {
            if ($media->path) {
                Storage::disk($media->disk ?: config('filesystems.default'))->delete($media->path);
            }

            $media->delete();
        }
    }

    /** @param  int|null  $ignoreId  Row to skip, so a post never collides with itself. */
    private function uniqueSlug(string $from, ?int $ignoreId = null): string
    {
        // Str::slug() transliterates Arabic to Latin, so an Arabic title still
        // yields a readable slug. It can still come back empty though — a title
        // of only punctuation or emoji — and an article with no slug would have
        // no reachable URL, hence the stub.
        $base = Str::slug($from) ?: 'article';
        $slug = $base;
        $i = 2;

        while (
            Blog::where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
