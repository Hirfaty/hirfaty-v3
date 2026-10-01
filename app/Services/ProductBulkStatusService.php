<?php

namespace App\Services;

use App\Models\Product;
use RuntimeException;

/**
 * Moves a hand-picked set of products between the storefront and the
 * archive from the admin catalogue.
 *
 * Only two moves are offered, each from exactly one status: archived
 * products can be put back on sale, and published ones taken off it. A
 * selection mixing statuses has no single move that makes sense for every
 * row, so it is refused as a whole rather than applied to whichever rows
 * happen to fit — the catalogue only offers these buttons for a uniform
 * selection, and the server holds the same line.
 */
class ProductBulkStatusService
{
    /** action => [required current status, new status] */
    public const TRANSITIONS = [
        'publish' => ['archived', 'published'],
        'archive' => ['published', 'archived'],
    ];

    /**
     * @param  array<int, int>  $ids
     * @return int how many products changed status
     *
     * @throws RuntimeException if any selected product isn't in the action's source status.
     */
    public function apply(string $action, array $ids): int
    {
        [$from, $to] = self::TRANSITIONS[$action];

        $mismatched = Product::whereIn('id', $ids)->where('status', '!=', $from)->exists();

        if ($mismatched) {
            throw new RuntimeException(
                "Every selected product must be {$from} to be {$to}. Nothing was changed."
            );
        }

        return Product::whereIn('id', $ids)->update(['status' => $to]);
    }
}
