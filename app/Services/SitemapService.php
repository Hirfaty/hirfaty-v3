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
 * Every page appears once per language (/fr, /ar, /en), each entry listing
 * its translations as hreflang alternates.
 *
 * Product and article photos ride along as <image:image> entries so they can
 * surface in Google Images.
 *
 * Cached for an hour: rebuilding it walks the whole catalogue, and a new
 * product appearing an hour late in the sitemap costs nothing.
 *
 * One file holds up to 50,000 URLs — about 16,000 pages at three languages; past that it must become a sitemap index.
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
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $xml->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        foreach (self::PAGES as $route) {
            $this->page($xml, fn (string $locale) => route($route, ['locale' => $locale]));
        }

        foreach ($this->categories() as $category) {
            $this->page($xml, fn (string $locale) => $this->seo->categoryUrl($category, $locale));
        }

        foreach ($this->products() as $product) {
            $this->page(
                $xml,
                fn (string $locale) => route('products.show', ['locale' => $locale, 'slug' => $product->slug]),
                $product->updated_at,
                $product->images->pluck('url')->filter()->take(self::IMAGES_PER_PAGE)->all(),
            );
        }

        foreach ($this->stores() as $store) {
            $this->page(
                $xml,
                fn (string $locale) => route('stores.show', ['locale' => $locale, 'slug' => $store->slug]),
                $store->updated_at,
                array_filter([$store->banner, $store->logo]),
            );
        }

        foreach (Blog::published()->with('media')->latest('published_at')->get() as $blog) {
            $this->page(
                $xml,
                fn (string $locale) => route('blog.show', ['locale' => $locale, 'slug' => $blog->slug]),
                $blog->updated_at,
                array_filter([$blog->coverUrl()]),
            );
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * One page, once per language. Each entry lists all its translations
     * (and the language-neutral x-default) as xhtml:link alternates — the
     * sitemap form of the hreflang tags on the page itself.
     *
     * @param  callable(string): string  $urlFor  The page's URL in a given language.
     * @param  array<int, string>  $images
     */
    private function page(XMLWriter $xml, callable $urlFor, ?DateTimeInterface $lastmod = null, array $images = []): void
    {
        $urls = [];

        foreach (LocaleService::SUPPORTED as $locale) {
            $urls[$locale] = $urlFor($locale);
        }

        $alternates = $this->seo->alternates($urls[LocaleService::DEFAULT]);

        foreach ($urls as $loc) {
            $xml->startElement('url');
            $xml->writeElement('loc', $loc);

            if ($lastmod) {
                $xml->writeElement('lastmod', $lastmod->format(DATE_ATOM));
            }

            foreach ($alternates as $hreflang => $href) {
                $xml->startElement('xhtml:link');
                $xml->writeAttribute('rel', 'alternate');
                $xml->writeAttribute('hreflang', $hreflang);
                $xml->writeAttribute('href', $href);
                $xml->endElement();
            }

            foreach ($images as $image) {
                $xml->startElement('image:image');
                $xml->writeElement('image:loc', $image);
                $xml->endElement();
            }

            $xml->endElement();
        }
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
     * Categories whose listing is not empty. A parent with subcategories is
     * left out whatever it holds: its URL only redirects to the first of them
     * (see Buyer\ProductController::index), and a sitemap should list where a
     * page lives, not a detour to it.
     */
    private function categories()
    {
        return Category::query()
            ->whereDoesntHave('children')
            ->whereIn('id', $this->publicProducts()->distinct()->pluck('category_id')->filter())
            ->orderBy('id')
            ->get();
    }
}
