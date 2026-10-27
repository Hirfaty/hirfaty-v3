<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;

/**
 * A product's shipping profile: weight, packed dimensions, and the customs
 * facts a carrier needs for a parcel leaving Morocco.
 *
 * The admin types figures in the unit they have to hand (a scale in grams, a
 * tape in metres), so they are stored as entered next to that unit, and every
 * consumer — a carrier quote, a label — reads them back through weightKg() /
 * dimensionsCm() here rather than doing its own arithmetic.
 */
class ProductShippingService
{
    public const WEIGHT_UNITS = ['g', 'kg'];

    public const DIMENSION_UNITS = ['cm', 'm'];

    /**
     * Every product ships from Hirfaty's Moroccan warehouse for now. Not
     * accepted from the form: it is the column default, and becomes an input
     * once sellers ship from their own countries.
     */
    public const DEFAULT_ORIGIN_COUNTRY = 'MA';

    /**
     * 6 digits (the international HS heading), or 8/10 for a national tariff
     * line; dots between digit pairs are accepted and stripped on save.
     */
    public const HS_CODE_RULE = 'regex:/^\d{4}\.?\d{2}(\.?\d{2}){0,2}$/';

    /** DHL Express's divisor for volumetric weight, in cm³ per kg. */
    public const VOLUMETRIC_DIVISOR = 5000;

    /**
     * Validation rules for the shipping fields of the admin product form.
     *
     * All optional, drafts or not: the catalogue predates these fields. DHL
     * prices a piece with no weight at its service's default weight
     * (WeightPricingService), so a blank never blocks a sale abroad — but it
     * can under-quote a heavy piece, which is why the admin show page flags a
     * product whose profile is incomplete.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $measure = ['nullable', 'numeric', 'gt:0', 'max:99999'];

        return [
            'weight' => $measure,
            'weight_unit' => ['nullable', Rule::in(self::WEIGHT_UNITS)],
            'length' => $measure,
            'width' => $measure,
            'height' => $measure,
            'dimension_unit' => ['nullable', Rule::in(self::DIMENSION_UNITS)],
            // Overrides the category's; blank inherits it.
            'hs_code' => ['nullable', 'string', self::HS_CODE_RULE],
            // Customs officers read English; "decor" or "gift" gets parcels held.
            'customs_description' => ['nullable', 'string', 'max:255'],
            'is_fragile' => ['nullable', 'boolean'],
            'handling_days' => ['nullable', 'integer', 'min:0', 'max:60'],

            // Per-variant overrides, in the product's units. Blank = inherit.
            'variants.*.weight' => $measure,
            'variants.*.length' => $measure,
            'variants.*.width' => $measure,
            'variants.*.height' => $measure,
        ];
    }

    /**
     * Product columns from validated form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function productAttributes(array $data): array
    {
        $hsCode = preg_replace('/\D/', '', (string) ($data['hs_code'] ?? ''));

        return [
            'weight' => $this->measure($data['weight'] ?? null),
            'weight_unit' => $data['weight_unit'] ?? 'kg',
            'length' => $this->measure($data['length'] ?? null),
            'width' => $this->measure($data['width'] ?? null),
            'height' => $this->measure($data['height'] ?? null),
            'dimension_unit' => $data['dimension_unit'] ?? 'cm',
            'hs_code' => $hsCode !== '' ? $hsCode : null,
            'customs_description' => trim((string) ($data['customs_description'] ?? '')) ?: null,
            'is_fragile' => (bool) ($data['is_fragile'] ?? false),
            'handling_days' => ($data['handling_days'] ?? null) === null || $data['handling_days'] === ''
                ? null
                : (int) $data['handling_days'],
        ];
    }

    /**
     * Variant override columns from one validated variant row.
     *
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>
     */
    public function variantAttributes(array $variant): array
    {
        return [
            'weight' => $this->measure($variant['weight'] ?? null),
            'length' => $this->measure($variant['length'] ?? null),
            'width' => $this->measure($variant['width'] ?? null),
            'height' => $this->measure($variant['height'] ?? null),
        ];
    }

    /** Shipping weight in kg, the variant's override winning over the product's. */
    public function weightKg(Product $product, ?ProductVariant $variant = null): ?float
    {
        $weight = $variant?->weight ?? $product->weight;

        if ($weight === null) {
            return null;
        }

        return $product->weight_unit === 'g' ? (float) $weight / 1000 : (float) $weight;
    }

    /**
     * Packed dimensions in cm, each axis falling back to the product's when
     * the variant leaves it blank. Null unless all three are known.
     *
     * @return array{length: float, width: float, height: float}|null
     */
    public function dimensionsCm(Product $product, ?ProductVariant $variant = null): ?array
    {
        $factor = $product->dimension_unit === 'm' ? 100 : 1;
        $result = [];

        foreach (['length', 'width', 'height'] as $axis) {
            $value = $variant?->{$axis} ?? $product->{$axis};

            if ($value === null) {
                return null;
            }

            $result[$axis] = (float) $value * $factor;
        }

        return $result;
    }

    /**
     * What a carrier bills: the greater of the actual and volumetric weight.
     * Bulky, light pieces — poufs, baskets, lanterns — are billed on volume.
     */
    public function chargeableWeightKg(Product $product, ?ProductVariant $variant = null): ?float
    {
        $actual = $this->weightKg($product, $variant);
        $dimensions = $this->dimensionsCm($product, $variant);

        if ($actual === null || $dimensions === null) {
            return $actual;
        }

        $volumetric = $dimensions['length'] * $dimensions['width'] * $dimensions['height']
            / self::VOLUMETRIC_DIVISOR;

        return max($actual, $volumetric);
    }

    /**
     * A basket as the pieces a carrier weighs and measures: one per unit, so
     * three bowls are three pieces. Each piece keeps its actual weight and
     * dimensions (null where unknown) beside the chargeable weight.
     *
     * One unit per piece is how a large piece ships anyway, and for small
     * ones it can only over-state the volume of a box the desk packs several
     * into — never under-bill the basket.
     *
     * @param  iterable<CartItem>  $lines
     * @return array<int, array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}>
     */
    public function pieces(iterable $lines): array
    {
        $pieces = [];

        foreach ($lines as $line) {
            if (! $line->product) {
                continue;
            }

            $piece = [
                'weight' => $this->weightKg($line->product, $line->variant),
                'chargeable' => $this->chargeableWeightKg($line->product, $line->variant),
                'dimensions' => $this->dimensionsCm($line->product, $line->variant),
            ];

            for ($unit = 0; $unit < max(1, (int) $line->quantity); $unit++) {
                $pieces[] = $piece;
            }
        }

        return $pieces;
    }

    /** The product's own HS code, else its category's. */
    public function hsCode(Product $product): ?string
    {
        return $product->hs_code ?? $product->category?->hs_code;
    }

    /**
     * True once a carrier could quote every purchasable form of the product:
     * each variant must resolve a weight and all three dimensions, from its
     * own overrides or the product's.
     */
    public function isComplete(Product $product): bool
    {
        $variants = $product->has_variants && $product->variants->isNotEmpty()
            ? $product->variants
            : collect([null]);

        return $variants->every(
            fn (?ProductVariant $variant) => $this->weightKg($product, $variant) !== null
                && $this->dimensionsCm($product, $variant) !== null
        );
    }

    private function measure(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
