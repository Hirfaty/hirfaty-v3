<?php

namespace App\Http\Resources;

use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One buyer review as shown on the public product page.
 *
 * The author's name is masked *here*, on the server, rather than in the page:
 * a name hidden only by the frontend would still sit in full in the Inertia
 * payload for anyone who opens dev tools. Nothing else about the buyer —
 * id, email, city, order — leaves this resource.
 *
 * @mixin ProductReview
 */
class ProductReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'author' => self::maskName($this->user?->first_name, $this->user?->last_name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * "Achraf Essaadi" → "A*** E."
     *
     * A fixed three stars rather than one per hidden letter, so the mask
     * doesn't give away the name's length. Multibyte-safe for Arabic names.
     * Null when there is no name at all — the page shows a generic label.
     */
    public static function maskName(?string $firstName, ?string $lastName): ?string
    {
        $first = trim((string) $firstName);
        $last = trim((string) $lastName);

        $parts = [];

        if ($first !== '') {
            $parts[] = mb_strtoupper(mb_substr($first, 0, 1)).'***';
        }

        if ($last !== '') {
            $parts[] = mb_strtoupper(mb_substr($last, 0, 1)).'.';
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
