<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use XMLWriter;

/**
 * /sitemap.xml — every public page a search engine should know about.
 *
 * Only what a guest can actually open is listed: published, priced products a
 * consumer may see (trade-only pieces 404 for a crawler), active shops with at
 * least one such product, categories that are not empty, and published
 * articles. Listing a URL that then 404s or shows an empty page wastes crawl
 * budget and reads as a low-quality site.
 *
 * Product and article photos ride along as <image:image> entries so they can
 * surface in Google Images.
 *
 * Cached for an hour: rebuilding it walks the whole catalogue, and a new
 * product appearing an hour late in the sitemap costs nothing.
 *
 * One file holds up to 50,000 URLs; past that it must become a sitemap index.
 */
class SitemapService
{
    public const CACHE_KEY = 'seo.sitemap';

    private const CACHE_SECONDS = 3600;

    private const IMAGES_PER_PAGE = 5;

    /** Static public pages, most important first. */
    private const PAGES = [
        'home', 'products.index', 'products.new', 'blog.index', 'about',
        'how-it-works', 'professional.show', 'business', 'sell', 'contact',
        'shipping-policy', 'terms', 'privacy', 'affiliate', 'rewards',
    ];

    public function __construct(private SeoService $seo) {}

    public function xml(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->build());
    }

    private function build(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        foreach (self::PAGES as $route) {
            $this->url($xml, route($route));
        }

        foreach ($this->categories() as $category) {
            $this->url($xml, $this->seo->categoryUrl($category));
        }

        foreach ($this->products() as $product) {
            $this->url(
                $xml,
                route('products.show', $product->slug),
                $product->updated_at,
                $product->images->pluck('url')->filter()->take(self::IMAGES_PER_PAGE)->all(),
            );
        }

        foreach ($this->stores() as $store) {
            $this->url(
                $xml,
                route('stores.show', $store->slug),
                $store->updated_at,
                array_filter([$store->banner, $store->logo]),
            );
        }

        foreach (Blog::published()->with('media')->latest('published_at')->get() as $blog) {
            $this->url(
                $xml,
                route('blog.show', $blog->slug),
                $blog->updated_at,
                array_filter([$blog->coverUrl()]),
            );
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @param  array<int, string>  $images */
    private function url(XMLWriter $xml, string $loc, ?DateTimeInterface $lastmod = null, array $images = []): void
    {
        $xml->startElement('url');
        $xml->writeElement('loc', $loc);

        if ($lastmod) {
            $xml->writeElement('lastmod', $lastmod->format(DATE_ATOM));
        }

        foreach ($images as $image) {
            $xml->startElement('image:image');
            $xml->writeElement('image:loc', $image);
            $xml->endElement();
        }

        $xml->endElement();
    }

    /** What a guest may see — the same gate as the listing and product page. */
    private function publicProducts()
    {
        return Product::storefront()->whereIn('visibility', ['both', Product::AUDIENCE_INDIVIDUAL]);
    }

    private function products()
    {
        return $this->publicProducts()
            ->whereNotNull('slug')
            ->with('images')
            ->orderBy('id')
            ->lazy(500);
    }

    private function stores()
    {
        return Store::query()
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->whereIn('id', $this->publicProducts()->select('store_id'))
            ->orderBy('id')
            ->get();
    }

    /**
     * Categories whose listing is not empty. A parent counts when any of its
     * children has a product, since selecting a parent lists its children's
     * pieces too (see Buyer\ProductController::index).
     */
    private function categories()
    {
        $withProducts = $this->publicProducts()->distinct()->pluck('category_id')->filter();

        return Category::query()
            ->with('children')
            ->orderBy('id')
            ->get()
            ->filter(fn (Category $category) => $withProducts->contains($category->id)
                || $category->children->pluck('id')->intersect($withProducts)->isNotEmpty())
            ->values();
    }
}
