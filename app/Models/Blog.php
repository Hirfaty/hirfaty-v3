<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An editorial article written in the admin panel.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content Sanitised HTML produced by the Tiptap editor.
 * @property string $status One of STATUSES.
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $author
 *
 * @mixin \Eloquent
 */
class Blog extends Model
{
    /** Written but not visible to the public. */
    public const STATUS_DRAFT = 'draft';

    /** Live on the public blog, provided `published_at` is in the past. */
    public const STATUS_PUBLISHED = 'published';

    /** Taken down without being deleted; keeps the slug reserved. */
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    /** Words an average reader gets through in a minute, for the "5 min read" label. */
    private const WORDS_PER_MINUTE = 200;

    protected $fillable = [
        'user_id', 'title', 'slug', 'excerpt', 'content', 'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The cover image, stored polymorphically like every other image in the
     * app. At most one row exists — BlogService replaces rather than appends.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('order');
    }

    /** @param  Builder<Blog>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function coverUrl(): ?string
    {
        return $this->media->first()?->url;
    }

    /**
     * Rough reading time in minutes, derived from the stripped HTML so markup
     * and image tags do not inflate the count. Never returns 0.
     *
     * Splits on whitespace rather than using str_word_count(), which counts
     * only ASCII letters and would score any Arabic article as a 1 min read.
     */
    public function readingMinutes(): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $this->content)) ?? '');
        $words = $text === '' ? 0 : count(explode(' ', $text));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
