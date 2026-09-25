<?php

namespace App\Services;

use App\Http\Resources\ProductReviewResource;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Everything a crawler or a link preview reads from a page's <head>: title,
 * description, canonical URL, robots rule, Open Graph / Twitter tags and
 * schema.org JSON-LD.
 *
 * Rendered server-side by resources/views/partials/seo.blade.php into the
 * first HTML response. That matters because the pages themselves are drawn
 * client-side (there is no SSR): WhatsApp, Facebook and most crawlers never
 * run the JavaScript, so a tag only React knows about does not exist for them.
 *
 * The tags are deliberately *not* Inertia-managed (no `data-inertia`): the
 * client leaves them alone, so a JavaScript-rendering crawler such as Google
 * still sees them after hydration. They go stale during in-app navigation,
 * which no visitor sees and no crawler does. `<title>` is the exception —
 * each page's <Head title> takes it over once the app boots.
 *
 * A page opts in with `->withViewData('seo', fn () => $seo->forX(...))`; the
 * closure only runs for a full HTML load, never for an Inertia XHR visit.
 * Pages that pass nothing get route-based defaults from resolve().
 */
class SeoService
{
    public const BRAND = 'Hirfaty';

    public const CURRENCY = 'MAD';

    public function __construct(private LocaleService $locales) {}

    /**
     * Public pages with their title key (reused from the keys the React
     * pages already set, so the server title and the tab title agree) and
     * their description key in lang/<locale>/seo.php.
     */
    private const PAGES = [
        'home' => ['titles.home', 'seo.home'],
        'products.index' => ['titles.catalogue', 'seo.catalogue'],
        'products.new' => ['buyer.new.title', 'seo.new'],
        'blog.index' => ['titles.blog', 'seo.blog'],
        'about' => ['titles.about', 'seo.about'],
        'how-it-works' => ['pages.how.title', 'seo.how'],
        'contact' => ['pages.contact.title', 'seo.contact'],
        'affiliate' => ['pages.affiliate.title', 'seo.affiliate'],
        'rewards' => ['pages.rewards.title', 'seo.rewards'],
        'privacy' => ['pages.privacy.title', 'seo.privacy'],
        'shipping-policy' => ['pages.shipping.title', 'seo.shipping'],
        'terms' => ['pages.terms.title', 'seo.terms'],
        'business' => ['common.auth.login.placeholder_business_title', 'seo.business'],
        'sell' => ['seller.join.page_title', 'seo.sell'],
        'professional.show' => ['pro.meta.title', 'seo.professional'],
    ];

    /**
     * The only routes a search engine may index. An allowlist rather than a
     * blocklist: any page added later — an account screen, a new admin
     * tool — is `noindex` until someone decides otherwise.
     */
    private const DETAIL_ROUTES = ['products.show', 'stores.show', 'blog.show'];

    /** Query parameters that still describe a distinct, indexable page. */
    private const CANONICAL_QUERY = [
        'products.index' => ['category_id'],
        'blog.index' => ['page'],
    ];

    private const OG_LOCALES = ['fr' => 'fr_MA', 'ar' => 'ar_MA', 'en' => 'en_US'];

    /**
     * The final tag set for the current request: route defaults, overlaid
     * with whatever the page supplied.
     *
     * A page can never lift a `noindex` the route rules imposed — that is
     * what keeps staging and private pages out of the index no matter what
     * a controller passes.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public function resolve(Request $request, array $page = []): array
    {
        $route = $request->route()?->getName();
        [$titleKey, $descriptionKey] = self::PAGES[$route] ?? [null, null];

        $seo = array_merge([
            'title' => $titleKey ? $this->title(__($titleKey)) : self::BRAND,
            'description' => $this->translated($descriptionKey ?? 'seo.default'),
            'canonical' => $this->canonical($request),
            'type' => 'website',
            'image' => $this->defaultImage(),
            'image_alt' => self::BRAND,
            'meta' => [],
            'json_ld' => $route === 'home' ? [$this->organizationSchema(), $this->websiteSchema()] : [],
        ], array_filter($page, fn ($value) => $value !== null));

        $seo['robots'] = $this->robots($request, $route);
        $seo['indexable'] = str_starts_with($seo['robots'], 'index');
        $seo['description'] = $this->clean($seo['description']);
        $seo['locale'] = self::OG_LOCALES[app()->getLocale()] ?? 'fr_MA';
        $seo['locale_alternates'] = collect(self::OG_LOCALES)->except(app()->getLocale())->values()->all();
        $seo['site_name'] = self::BRAND;
        $seo['alternates'] = $seo['indexable'] ? $this->alternates($seo['canonical']) : [];

        return $seo;
    }

    /**
     * hreflang links: the same page in each language, plus `x-default` — the
     * language-neutral URL, which sends each visitor to their own language.
     * They tell a search engine the three URLs are translations of one page
     * (not duplicates) and which to show to whom.
     *
     * Empty for a page with no language in its URL: there is nothing to
     * point at.
     *
     * @return array<string, string> hreflang => absolute URL
     */
    public function alternates(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $first = explode('/', ltrim($path, '/'))[0];

        if (! $this->locales->isSupported($first)) {
            return [];
        }

        $links = [];

        foreach (LocaleService::SUPPORTED as $locale) {
            $links[$locale] = url($this->locales->swap($url, $locale));
        }

        $links['x-default'] = url($this->locales->strip($url));

        return $links;
    }

    /**
     * Whether this deployment may be indexed at all. False everywhere but
     * production, and always false on the staging host.
     */
    public function indexingAllowed(Request $request): bool
    {
        return (bool) config('hirfaty.indexable')
            && $request->getHost() !== config('hirfaty.staging_host');
    }

    /**
     * @param  array<int, ProductReview>  $reviews  The page of reviews the product page already loaded.
     * @param  array{average: float|null, count: int}  $reviewSummary
     * @return array<string, mixed>
     */
    public function forProduct(Product $product, array $reviewSummary, array $reviews = []): array
    {
        $url = route('products.show', $product->slug);
        $images = $product->images->pluck('url')->filter()->take(10)->values()->all();
        $offer = $this->offer($product, $url);
        $storeName = $product->store?->store_name;

        $description = $product->description ?: __('seo.product', [
            'product' => $product->name,
            'store' => $storeName ?? self::BRAND,
        ]);

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $this->clean($description, 5000),
            'image' => $images ?: null,
            'sku' => $product->sku ?: null,
            'url' => $url,
            'brand' => $storeName ? ['@type' => 'Brand', 'name' => $storeName] : null,
            'category' => $product->category?->translated_name,
            'offers' => $offer['schema'],
        ]);

        if ($reviewSummary['count'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $reviewSummary['average'],
                'reviewCount' => $reviewSummary['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ];
            $schema['review'] = collect($reviews)->take(5)->map(fn (ProductReview $review) => array_filter([
                '@type' => 'Review',
                'author' => [
                    '@type' => 'Person',
                    // Same mask as the public page — never the full name.
                    'name' => ProductReviewResource::maskName($review->user?->first_name, $review->user?->last_name)
                        ?? __('buyer.detail.reviews_anonymous'),
                ],
                'datePublished' => $review->created_at?->toDateString(),
                'reviewBody' => $review->comment ?: null,
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => $review->rating,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ],
            ]))->values()->all();
        }

        $crumbs = [
            [__('seo.breadcrumb.home'), route('home')],
            [__('titles.catalogue'), route('products.index')],
        ];

        if ($product->category) {
            $crumbs[] = [$product->category->translated_name, $this->categoryUrl($product->category)];
        }

        $crumbs[] = [$product->name, $url];

        return [
            'title' => $this->title($product->name),
            'description' => $description,
            'canonical' => $url,
            'type' => 'product',
            'image' => $images[0] ?? null,
            'image_alt' => $product->name,
            'meta' => array_filter([
                'product:price:amount' => $offer['price'],
                'product:price:currency' => $offer['price'] !== null ? self::CURRENCY : null,
                'product:availability' => $offer['in_stock'] ? 'in stock' : 'out of stock',
            ]),
            'json_ld' => [$schema, $this->breadcrumbSchema($crumbs)],
        ];
    }

    /** @return array<string, mixed> */
    public function forStore(Store $store): array
    {
        $url = route('stores.show', $store->slug);
        $city = $store->city?->name;

        $description = $store->description ?: __($city ? 'seo.store_city' : 'seo.store', [
            'store' => $store->store_name,
            'city' => $city,
        ]);

        return [
            'title' => $this->title($store->store_name),
            'description' => $description,
            'canonical' => $url,
            'type' => 'profile',
            'image' => $store->banner ?: $store->logo,
            'image_alt' => $store->store_name,
            'json_ld' => [$this->breadcrumbSchema([
                [__('seo.breadcrumb.home'), route('home')],
                [$store->store_name, $url],
            ])],
        ];
    }

    /** @return array<string, mixed> */
    public function forBlog(Blog $blog): array
    {
        $url = route('blog.show', $blog->slug);
        $image = $blog->coverUrl();
        $description = $blog->excerpt ?: (string) $blog->content;
        $author = $blog->author ? trim("{$blog->author->first_name} {$blog->author->last_name}") : null;

        $article = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => Str::limit($blog->title, 110, ''),
            'description' => $this->clean($description),
            'image' => $image ? [$image] : null,
            'datePublished' => $blog->published_at?->toIso8601String(),
            'dateModified' => ($blog->updated_at ?? $blog->published_at)?->toIso8601String(),
            'author' => $author
                ? ['@type' => 'Person', 'name' => $author]
                : ['@type' => 'Organization', 'name' => self::BRAND, 'url' => route('home')],
            'publisher' => $this->organizationSchema(withContext: false),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'inLanguage' => app()->getLocale(),
        ]);

        return [
            'title' => $this->title($blog->title),
            'description' => $description,
            'canonical' => $url,
            'type' => 'article',
            'image' => $image,
            'image_alt' => $blog->title,
            'meta' => array_filter([
                'article:published_time' => $blog->published_at?->toIso8601String(),
                'article:modified_time' => $blog->updated_at?->toIso8601String(),
            ]),
            'json_ld' => [$article, $this->breadcrumbSchema([
                [__('seo.breadcrumb.home'), route('home')],
                [__('titles.blog'), route('blog.index')],
                [$blog->title, $url],
            ])],
        ];
    }

    /**
     * The catalogue, optionally narrowed to one category. A category listing
     * is a page worth ranking on its own ("poterie marocaine"), so it gets
     * its own title and description rather than the generic catalogue copy.
     *
     * @return array<string, mixed>
     */
    public function forCatalogue(?Category $category): array
    {
        if (! $category) {
            return [];
        }

        $name = $category->translated_name;

        return [
            'title' => $this->title("{$name} — ".__('titles.catalogue')),
            'description' => __('seo.catalogue_category', ['category' => $name]),
            'canonical' => $this->categoryUrl($category),
            'json_ld' => [$this->breadcrumbSchema([
                [__('seo.breadcrumb.home'), route('home')],
                [__('titles.catalogue'), route('products.index')],
                [$name, $this->categoryUrl($category)],
            ])],
        ];
    }

    /** In the current language unless another is asked for (the sitemap lists all three). */
    public function categoryUrl(Category $category, ?string $locale = null): string
    {
        return route('products.index', array_filter([
            'locale' => $locale,
            'category_id' => $category->id,
        ]));
    }

    /**
     * robots.txt. Outside production it shuts the door entirely; in
     * production it keeps crawlers out of private and transactional pages
     * (which are `noindex` anyway) and points them at the sitemap.
     */
    public function robotsTxt(Request $request): string
    {
        if (! $this->indexingAllowed($request)) {
            return "User-agent: *\nDisallow: /\n";
        }

        $disallow = [
            '/admin', '/seller', '/account', '/orders', '/wishlist', '/cart',
            '/checkout', '/payment_method', '/onboarding', '/settings',
            '/login', '/register', '/forgot-password', '/reset-password',
            '/two-factor-challenge', '/email', '/auth/', '/search/',
            '/brochure/', '/preview/',
        ];

        return "User-agent: *\n"
            .collect($disallow)->map(fn ($path) => "Disallow: {$path}")->implode("\n")
            ."\n\nSitemap: ".route('sitemap')."\n";
    }

    /** "Vase bleu" → "Vase bleu — Hirfaty"; the same shape app.tsx gives the tab. */
    private function title(?string $title): string
    {
        return $title ? "{$title} — ".self::BRAND : self::BRAND;
    }

    private function translated(string $key): string
    {
        $value = __($key);

        return $value === $key ? __('seo.default') : $value;
    }

    private function robots(Request $request, ?string $route): string
    {
        if (! $this->indexingAllowed($request)) {
            return 'noindex, nofollow';
        }

        $public = $route !== null
            && (array_key_exists($route, self::PAGES) || in_array($route, self::DETAIL_ROUTES, true));

        if (! $public) {
            return 'noindex, nofollow';
        }

        // Search results are infinite and thin — crawl through them, never index them.
        if ($request->filled('search')) {
            return 'noindex, follow';
        }

        return 'index, follow, max-image-preview:large';
    }

    /**
     * The URL without tracking junk, filters or "load more" pages — except
     * the few parameters that genuinely make a distinct page.
     */
    private function canonical(Request $request): string
    {
        $keep = self::CANONICAL_QUERY[$request->route()?->getName()] ?? [];

        $query = collect(Arr::only($request->query(), $keep))
            ->filter(fn ($value, $key) => is_scalar($value) && $value !== '' && ! ($key === 'page' && (int) $value <= 1))
            ->all();

        return $request->url().($query ? '?'.http_build_query($query) : '');
    }

    /** Plain text, single-spaced, cut on a word boundary. */
    private function clean(?string $text, int $limit = 160): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return Str::limit($plain, $limit - 1, '…', preserveWords: true);
    }

    /**
     * The consumer price and availability a guest (and so a crawler) sees.
     * Trade prices are never exposed here: crawlers are not verified
     * professional buyers.
     *
     * A product with variants priced differently is an AggregateOffer
     * (low–high); otherwise a single Offer.
     *
     * @return array{schema: array<string, mixed>|null, price: string|null, in_stock: bool}
     */
    private function offer(Product $product, string $url): array
    {
        if ($product->base_price === null) {
            return ['schema' => null, 'price' => null, 'in_stock' => false];
        }

        $base = (float) $product->base_price;
        $sale = $product->sale_price !== null ? (float) $product->sale_price : null;
        $retail = $sale !== null && $sale < $base ? $sale : $base;

        $variants = $product->has_variants && $product->relationLoaded('variants')
            ? $product->variants->where('is_active', true)
            : collect();

        if ($variants->isNotEmpty()) {
            $prices = $variants->map(fn ($variant) => $variant->price_override !== null ? (float) $variant->price_override : $retail);
            $inStock = $variants->contains(fn ($variant) => (int) $variant->stock > 0);
        } else {
            $prices = collect([$retail]);
            $inStock = (int) $product->stock > 0;
        }

        $availability = $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
        $low = $prices->min();
        $high = $prices->max();

        $schema = $low === $high
            ? [
                '@type' => 'Offer',
                'price' => $this->money($low),
                'priceCurrency' => self::CURRENCY,
            ]
            : [
                '@type' => 'AggregateOffer',
                'lowPrice' => $this->money($low),
                'highPrice' => $this->money($high),
                'offerCount' => $prices->count(),
                'priceCurrency' => self::CURRENCY,
            ];

        $schema += [
            'availability' => $availability,
            'itemCondition' => 'https://schema.org/NewCondition',
            'url' => $url,
            'seller' => ['@type' => 'Organization', 'name' => self::BRAND],
        ];

        return ['schema' => $schema, 'price' => $this->money($low), 'in_stock' => $inStock];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $crumbs  [name, url] pairs, root first.
     * @return array<string, mixed>
     */
    private function breadcrumbSchema(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn ($crumb, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function organizationSchema(bool $withContext = true): array
    {
        return array_filter([
            '@context' => $withContext ? 'https://schema.org' : null,
            '@type' => 'Organization',
            'name' => self::BRAND,
            'url' => route('home'),
            'logo' => asset('apple-touch-icon.png'),
        ]);
    }

    /** @return array<string, mixed> */
    private function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => self::BRAND,
            'url' => route('home'),
            'inLanguage' => ['fr', 'ar', 'en'],
        ];
    }

    private function defaultImage(): string
    {
        return asset('og-image.jpg');
    }
}
