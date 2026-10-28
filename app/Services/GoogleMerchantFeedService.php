<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * /feeds/google/{language}.xml — the catalogue as a Google Merchant Center
 * product feed (RSS 2.0 with the `g:` namespace), fetched on a schedule by
 * Merchant Center.
 *
 * Crawling the product pages' JSON-LD only ever picks up part of the
 * catalogue, and cannot list a variant product at all when its variants are
 * priced differently (the page carries an AggregateOffer, a range, and
 * Merchant Center needs one price per item). Here every active variant is its
 * own item, grouped under its product by `item_group_id`.
 *
 * Only what a guest may buy is listed — the same gate as the sitemap — and at
 * the consumer price: trade prices are never exposed to a crawler. Prices are
 * MAD, the currency the storefront shows a crawler (CurrencyService), so the
 * feed and the landing page always agree; Merchant Center converts for other
 * target countries itself.
 *
 * Handmade pieces have no barcode, hence `identifier_exists=no` on every item
 * and the shop as `brand`.
 *
 * Delivery is priced on each item (`g:shipping`, one per country in
 * `hirfaty.google_feed_shipping_countries`) by DeliveryFeeService at what
 * checkout bills for one unit, so Merchant Center needs no shipping table of
 * its own, and the price Google shows follows the cards — the DHL fuel % the
 * desk edits weekly included — within the hour.
 *
 * Cached for an hour, like the sitemap: Merchant Center fetches it daily.
 */
class GoogleMerchantFeedService
{
    public const CACHE_KEY = 'seo.google_feed.';

    private const CACHE_SECONDS = 3600;

    /** Merchant Center truncates past these. */
    private const TITLE_LIMIT = 150;

    private const DESCRIPTION_LIMIT = 5000;

    private const ADDITIONAL_IMAGES = 10;

    /** Variant axes Merchant Center understands, by our option type. */
    private const VARIANT_ATTRIBUTES = [
        'color' => 'g:color',
        'dimension' => 'g:size',
        'material' => 'g:material',
        'pattern' => 'g:pattern',
    ];

    public function __construct(
        private ProductShippingService $shipping,
        private DeliveryFeeService $delivery,
    ) {}

    public function xml(string $language): string
    {
        return Cache::remember(self::CACHE_KEY.$language, self::CACHE_SECONDS, fn () => $this->build($language));
    }

    private function build(string $language): string
    {
        // Descriptions fall back to a translated sentence, and category names
        // resolve in the app locale — both must be the feed's language.
        $previous = app()->getLocale();
        app()->setLocale($language);

        try {
            $xml = new XMLWriter;
            $xml->openMemory();
            $xml->setIndent(true);
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('rss');
            $xml->writeAttribute('version', '2.0');
            $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
            $xml->startElement('channel');
            $xml->writeElement('title', SeoService::BRAND);
            $xml->writeElement('link', route('home', ['locale' => $language]));
            $xml->writeElement('description', __('seo.default'));

            $quote = $this->delivery->countryQuoter(config('hirfaty.google_feed_shipping_countries', []));

            foreach ($this->products() as $product) {
                foreach ($this->items($product, $language, $quote) as $item) {
                    $this->writeItem($xml, $item);
                }
            }

            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();

            return $xml->outputMemory();
        } finally {
            app()->setLocale($previous);
        }
    }

    /** What a guest may buy — the same gate as the sitemap and product page. */
    private function products()
    {
        return Product::storefront()
            ->whereIn('visibility', ['both', Product::AUDIENCE_INDIVIDUAL])
            ->whereNotNull('slug')
            ->with(['images', 'store', 'category', 'variants.options', 'variants.media'])
            ->orderBy('id')
            ->lazy(200);
    }

    /**
     * One item for a simple product, one per active variant otherwise. A
     * product with no photo is left out: Merchant Center rejects an item
     * without `image_link` anyway.
     *
     * @param  Closure(iterable<CartItem>): array<string, array{service: string, price: float}>  $quote
     * @return array<int, array<string, string|array<int, string|array<string, string>>>>
     */
    private function items(Product $product, string $language, Closure $quote): array
    {
        $images = $this->imageUrls($product->images);

        $variants = $product->has_variants
            ? $product->variants->where('is_active', true)->values()
            : collect();

        $base = (float) $product->base_price;
        $sale = $product->sale_price !== null && (float) $product->sale_price < $base
            ? (float) $product->sale_price
            : null;

        $url = route('products.show', ['locale' => $language, 'slug' => $product->slug]);

        $shared = array_filter([
            'g:description' => $this->description($product),
            'g:condition' => 'new',
            'g:brand' => Str::limit((string) ($product->store?->store_name ?: SeoService::BRAND), 70, ''),
            'g:identifier_exists' => 'no',
            'g:product_type' => $product->category?->nameFor($language),
        ]);

        if ($variants->isEmpty()) {
            if ($images === []) {
                return [];
            }

            return [array_filter([
                'g:id' => (string) $product->id,
                'g:title' => Str::limit($product->name, self::TITLE_LIMIT, ''),
                'g:link' => $url,
                ...$this->imageFields($images),
                'g:availability' => (int) $product->stock > 0 ? 'in_stock' : 'out_of_stock',
                ...$this->priceFields($base, $sale),
                ...$shared,
                'g:shipping_weight' => $this->weight($product),
                ...$this->shippingFields($quote, $product),
            ])];
        }

        return $variants->map(function (ProductVariant $variant) use ($product, $images, $base, $sale, $language, $shared, $quote) {
            $own = $this->imageUrls($variant->media->where('type', 'image'));
            $gallery = array_values(array_unique([...$own, ...$images]));

            if ($gallery === []) {
                return null;
            }

            $label = $variant->label();
            $title = $label !== '' ? "{$product->name} — {$label}" : $product->name;

            // An override is the variant's own full price; the product's sale
            // only applies to variants that inherit its price.
            [$price, $salePrice] = $variant->price_override !== null
                ? [(float) $variant->price_override, null]
                : [$base, $sale];

            return array_filter([
                'g:id' => "{$product->id}-{$variant->id}",
                'g:item_group_id' => (string) $product->id,
                'g:title' => Str::limit($title, self::TITLE_LIMIT, ''),
                'g:link' => route('products.show', ['locale' => $language, 'slug' => $product->slug, 'variant' => $variant->id]),
                ...$this->imageFields($gallery),
                'g:availability' => (int) $variant->stock > 0 ? 'in_stock' : 'out_of_stock',
                ...$this->priceFields($price, $salePrice),
                ...$shared,
                ...$this->variantAttributes($variant),
                'g:shipping_weight' => $this->weight($product, $variant),
                ...$this->shippingFields($quote, $product, $variant),
            ]);
        })->filter()->values()->all();
    }

    /**
     * A list repeats the field; a list entry that is itself keyed (a
     * `g:shipping`) is written as a group of sub-fields.
     *
     * @param  array<string, string|array<int, string|array<string, string>>>  $item
     */
    private function writeItem(XMLWriter $xml, array $item): void
    {
        $xml->startElement('item');

        foreach ($item as $field => $value) {
            foreach ((array) $value as $one) {
                if (! is_array($one)) {
                    $xml->writeElement($field, $one);

                    continue;
                }

                $xml->startElement($field);

                foreach ($one as $subField => $subValue) {
                    $xml->writeElement($subField, $subValue);
                }

                $xml->endElement();
            }
        }

        $xml->endElement();
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, string>
     */
    private function imageUrls(Collection $media): array
    {
        return $media->pluck('url')
            ->filter()
            ->map(fn (string $url) => Str::startsWith($url, ['http://', 'https://']) ? $url : url($url))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $images
     * @return array<string, string|array<int, string>>
     */
    private function imageFields(array $images): array
    {
        return array_filter([
            'g:image_link' => $images[0],
            'g:additional_image_link' => array_slice($images, 1, self::ADDITIONAL_IMAGES),
        ]);
    }

    /** @return array<string, string> */
    private function priceFields(float $price, ?float $sale): array
    {
        return array_filter([
            'g:price' => $this->money($price),
            'g:sale_price' => $sale !== null && $sale < $price ? $this->money($sale) : null,
        ]);
    }

    /** @return array<string, string> */
    private function variantAttributes(ProductVariant $variant): array
    {
        $attributes = [];

        foreach ($variant->options as $option) {
            $field = self::VARIANT_ATTRIBUTES[$option->type] ?? null;

            if ($field !== null && ! isset($attributes[$field]) && $option->value !== '') {
                $attributes[$field] = Str::limit($option->value, 100, '');
            }
        }

        return $attributes;
    }

    private function description(Product $product): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) HtmlSanitizer::toText($product->description)));

        if ($text === '') {
            $text = __('seo.product', [
                'product' => $product->name,
                'store' => $product->store?->store_name ?? SeoService::BRAND,
            ]);
        }

        return Str::limit($text, self::DESCRIPTION_LIMIT - 1, '…', preserveWords: true);
    }

    private function weight(Product $product, ?ProductVariant $variant = null): ?string
    {
        $kg = $this->shipping->weightKg($product, $variant);

        return $kg !== null && $kg > 0 ? number_format($kg, 3, '.', '').' kg' : null;
    }

    /**
     * One `g:shipping` per country the item can be delivered to, at what
     * checkout bills for one unit of it.
     *
     * @param  Closure(iterable<CartItem>): array<string, array{service: string, price: float}>  $quote
     * @return array<string, array<int, array<string, string>>>
     */
    private function shippingFields(Closure $quote, Product $product, ?ProductVariant $variant = null): array
    {
        $line = (new CartItem(['quantity' => 1]))
            ->setRelation('product', $product)
            ->setRelation('variant', $variant);

        $entries = [];

        foreach ($quote([$line]) as $country => ['service' => $service, 'price' => $price]) {
            $entries[] = [
                'g:country' => $country,
                'g:service' => $service,
                'g:price' => $this->money($price),
            ];
        }

        return $entries === [] ? [] : ['g:shipping' => $entries];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '').' '.SeoService::CURRENCY;
    }
}
