<?php

namespace App\Services;

use App\Models\ProductVariantOption;
use Illuminate\Validation\ValidationException;

/**
 * Shape rules for a product's variants, shared by create and edit.
 */
class ProductVariantService
{
    /**
     * Turn submitted options into rows, forcing the canonical axis name for
     * every type but `other`.
     *
     * The axis is derived server-side rather than trusted from the form, so
     * "Couleur" cannot arrive as "couleur"/"Color" and split one selector row
     * into several that no variant can satisfy together.
     *
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, string>>
     */
    public function normaliseOptions(array $options): array
    {
        return collect($options)
            ->map(fn (array $o) => [
                'type' => $o['type'],
                'name' => ProductVariantOption::axisFor($o['type'])
                    ?? trim((string) ($o['name'] ?? '')) ?: 'Option',
                'value' => trim((string) $o['value']),
            ])
            ->all();
    }

    /**
     * Structural rules a set of variants must satisfy, checked before anything
     * is written.
     *
     * Both failures here are silent-corruption bugs rather than cosmetic ones:
     *
     *  - Ragged axes (one variant on "Couleur", another on "Dimension") make
     *    the storefront render selector rows that no single variant answers,
     *    so the product cannot be added to a basket at all.
     *  - Two variants on the same combination leave the shopper picking one
     *    row while stock silently splits across two, and the storefront can
     *    only ever resolve the first.
     *
     * Every variant is measured against the union of axes in use, not against
     * the first row: adding "Dimension" to a colour-only product leaves the old
     * colour-only rows behind, and comparing against whichever row came first
     * blamed the new, complete rows instead of the stale ones. Every offending
     * row is reported at once, with what it is missing.
     *
     * @param  array<int, array<string, mixed>>  $variants
     *
     * @throws ValidationException
     */
    public function assertCoherent(array $variants): void
    {
        $variants = array_values($variants);

        if ($variants === []) {
            return;
        }

        $axesOf = fn (array $variant): array => collect($this->normaliseOptions($variant['options']))
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        // First-seen order, so the message lists axes the way the form does.
        $expected = collect($variants)->flatMap($axesOf)->unique()->values()->all();

        $errors = [];
        $seen = [];

        foreach ($variants as $i => $variant) {
            $missing = array_values(array_diff($expected, $axesOf($variant)));

            if ($missing !== []) {
                $errors["variants.{$i}.options"] = count($missing) === 1
                    ? "Il manque l’option {$missing[0]} : complétez cette déclinaison ou supprimez-la."
                    : 'Il manque les options '.implode(', ', $missing).' : complétez cette déclinaison ou supprimez-la.';

                continue;
            }

            // Order-independent, so Rouge/4m and 4m/Rouge count as the same.
            $signature = collect($this->normaliseOptions($variant['options']))
                ->map(fn (array $o) => $o['name'].'='.mb_strtolower($o['value']))
                ->sort()
                ->implode('|');

            if (isset($seen[$signature])) {
                $errors["variants.{$i}.options"] = 'Cette combinaison existe déjà dans une autre déclinaison.';

                continue;
            }

            $seen[$signature] = true;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
