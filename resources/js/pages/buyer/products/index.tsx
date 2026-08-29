import { Link, router, usePage } from '@inertiajs/react';
import { Search, SlidersHorizontal, Star } from 'lucide-react';
import { useState } from 'react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import type { PaginatedData, Category } from '@/types';
import { Product } from '@/types';
import type { BuyerProductResource } from '@/types/extended_models';

type Filters = {
    search?: string;
    category_id?: string;
    max_price?: string;
    min_rating?: string;
    sort?: string;
};

const Index = ({
    products,
    categories,
    filters,
}: {
    products: PaginatedData<BuyerProductResource>;
    categories: Category[];
    filters: Filters;
}) => {
    console.log('products', products);
    const [search, setSearch] = useState(filters.search ?? '');
    const [catId, setCatId] = useState(filters.category_id ?? '');
    const [maxPrice, setMaxPrice] = useState(filters.max_price ?? '3000');
    const [minRating, setMinRating] = useState(Number(filters.min_rating ?? 0));
    // const [sort, setSort] = useState(filters.sort ?? 'score');

    // return <div>hl</div>;

    // ─── Apply filters — go to page 1 ────────────────────────────
    const applyFilters = (overrides: Partial<Filters> = {}) => {
        router.get(
            route('products.index'),
            {
                search,
                category_id: catId,
                max_price: maxPrice,
                min_rating: minRating > 0 ? minRating : undefined,
                // sort,
                ...overrides,
            },
            {
                preserveScroll: false,
                preserveState: false,
            },
        );
    };

    // ─── Load more — append next page data ───────────────────────
    const loadMore = () => {
        if (!products.next_page_url) {
            return;
        }

        router.get(
            products.next_page_url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['products'],
            },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setCatId('');
        setMaxPrice('3000');
        setMinRating(0);
        // setSort('score');
        router.get(route('products.index'));
    };

    const handleCategory = (id: string) => {
        setCatId(id);
        applyFilters({ category_id: id });
    };

    const handleRating = (n: number) => {
        const next = n === minRating ? 0 : n;
        setMinRating(next);
        applyFilters({ min_rating: next > 0 ? String(next) : undefined });
    };

    return (
        <div className="paper-grain min-h-screen bg-cream">
            <SiteHeader />

            {/* Page heading */}
            <section className="container pt-10 pb-8 md:pt-14 md:pb-10">
                <div className="grid grid-cols-12 items-end gap-4 md:gap-6">
                    <div className="col-span-12 md:col-span-8">
                        <p className="eyebrow mb-3">Le marché</p>
                        <h1 className="h-editorial text-4xl text-ink sm:text-5xl md:text-7xl">
                            Tout ce qui se fait à la main,{' '}
                            <span className="text-terracotta italic">
                                en ce moment
                            </span>
                            .
                        </h1>
                    </div>
                    <div className="col-span-12 text-sm text-ink-soft md:col-span-4 md:text-right">
                        <span className="font-display text-xl text-ink md:text-2xl">
                            {products.total}
                        </span>{' '}
                        pièces · mises à jour aujourd'hui
                    </div>
                </div>
            </section>

            {/* Sticky filter bar */}
            <div className="sticky top-0 z-30 border-y border-ink/15 bg-cream/95 backdrop-blur">
                <div className="container flex items-center gap-3 overflow-x-auto py-3 md:gap-4">
                    <SlidersHorizontal className="h-4 w-4 shrink-0 text-ink-soft" />

                    {/* Category pills */}
                    <div className="flex shrink-0 items-center gap-1">
                        <button
                            onClick={() => handleCategory('')}
                            className={`px-3 py-1.5 text-sm whitespace-nowrap transition-colors ${
                                catId === ''
                                    ? 'bg-ink text-cream'
                                    : 'text-ink/75 hover:text-ink'
                            }`}
                        >
                            Tout
                        </button>
                        {categories.map((c) => (
                            <button
                                key={c.id}
                                onClick={() => handleCategory(String(c.id))}
                                className={`px-3 py-1.5 text-sm whitespace-nowrap transition-colors ${
                                    catId === String(c.id)
                                        ? 'bg-ink text-cream'
                                        : 'text-ink/75 hover:text-ink'
                                }`}
                            >
                                {c.translated_name}
                            </button>
                        ))}
                    </div>

                    <span className="h-5 w-px shrink-0 bg-ink/15" />

                    {/* Sort */}
                    {/* <div className="flex shrink-0 items-center gap-3 text-sm text-ink-soft">
                        <span>Trier :</span>
                        {sortOptions.map((o) => (
                            <button
                                key={o.value}
                                onClick={() => handleSort(o.value)}
                                className={`transition-colors ${
                                    sort === o.value
                                        ? 'link-underline text-ink'
                                        : 'hover:text-ink'
                                }`}
                            >
                                {o.label}
                            </button>
                        ))}
                    </div> */}
                </div>
            </div>

            <section className="container grid grid-cols-12 gap-8 py-8 md:gap-12 md:py-14">
                {/* Mobile filters */}
                <div className="col-span-12 md:hidden">
                    <details className="border border-ink/15 bg-paper/70 p-4">
                        <summary className="flex cursor-pointer list-none items-center justify-between text-sm text-ink">
                            <span className="eyebrow mb-0">
                                Filtres avancés
                            </span>
                            <span className="text-xs text-ink-soft">
                                Ouvrir
                            </span>
                        </summary>
                        <div className="mt-4 space-y-6">
                            <div>
                                <p className="eyebrow mb-3">Recherche</p>
                                <div className="relative">
                                    <Search
                                        className="absolute top-1/2 left-0 h-4 w-4 -translate-y-1/2 text-ink-soft"
                                        strokeWidth={1.5}
                                    />
                                    <input
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        onKeyDown={(e) =>
                                            e.key === 'Enter' && applyFilters()
                                        }
                                        placeholder="Caftan, babouches…"
                                        className="w-full border-0 border-b border-ink/30 bg-transparent pb-2 pl-6 text-sm placeholder:text-ink-soft/70 focus:border-ink focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <div className="mb-3 flex items-baseline justify-between">
                                    <p className="eyebrow">Prix</p>
                                    <span className="text-sm text-ink">
                                        jusqu'à {maxPrice} MAD
                                    </span>
                                </div>
                                <input
                                    type="range"
                                    min={50}
                                    max={3000}
                                    step={10}
                                    value={maxPrice}
                                    onChange={(e) =>
                                        setMaxPrice(e.target.value)
                                    }
                                    onMouseUp={() => applyFilters()}
                                    onTouchEnd={() => applyFilters()}
                                    className="w-full accent-terracotta"
                                />
                                <div className="mt-2 flex justify-between text-xs text-ink-soft">
                                    <span>50</span>
                                    <span>3000</span>
                                </div>
                            </div>

                            <div>
                                <p className="eyebrow mb-3">Note minimum</p>
                                <div className="flex gap-1">
                                    {[1, 2, 3, 4, 5].map((n) => (
                                        <button
                                            key={n}
                                            onClick={() => handleRating(n)}
                                            aria-label={`${n} étoile`}
                                            className="p-1"
                                        >
                                            <Star
                                                className={`h-4 w-4 ${n <= minRating ? 'fill-terracotta text-terracotta' : 'text-ink/30'}`}
                                                strokeWidth={1.5}
                                            />
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <button
                                onClick={resetFilters}
                                className="link-underline text-sm text-ink-soft hover:text-ink"
                            >
                                Réinitialiser les filtres
                            </button>
                        </div>
                    </details>
                </div>

                {/* Sidebar */}
                <aside className="col-span-12 hidden space-y-10 md:col-span-3 md:block">
                    {/* Search */}
                    <div>
                        <p className="eyebrow mb-4">Recherche</p>
                        <div className="relative">
                            <Search
                                className="absolute top-1/2 left-0 h-4 w-4 -translate-y-1/2 text-ink-soft"
                                strokeWidth={1.5}
                            />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters()
                                }
                                placeholder="Caftan, babouches…"
                                className="w-full border-0 border-b border-ink/30 bg-transparent pb-2 pl-6 text-sm placeholder:text-ink-soft/70 focus:border-ink focus:outline-none"
                            />
                        </div>
                    </div>

                    {/* Category */}
                    <div>
                        <p className="eyebrow mb-4">Catégorie</p>
                        <ul className="space-y-2.5 text-sm">
                            <li>
                                <button
                                    onClick={() => handleCategory('')}
                                    className="group flex items-center gap-3 text-ink/80 hover:text-ink"
                                >
                                    <span
                                        className={`h-3.5 w-3.5 border border-ink/40 ${catId === '' ? 'border-ink bg-ink' : 'bg-transparent'}`}
                                    />
                                    Tout
                                </button>
                            </li>
                            {categories.map((c) => (
                                <li key={c.id}>
                                    <button
                                        onClick={() =>
                                            handleCategory(String(c.id))
                                        }
                                        className="group flex items-center gap-3 text-ink/80 hover:text-ink"
                                    >
                                        <span
                                            className={`h-3.5 w-3.5 border border-ink/40 ${catId === String(c.id) ? 'border-ink bg-ink' : 'bg-transparent'}`}
                                        />
                                        {c.translated_name}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Price */}
                    <div>
                        <div className="mb-4 flex items-baseline justify-between">
                            <p className="eyebrow">Prix</p>
                            <span className="text-sm text-ink">
                                jusqu'à {maxPrice} MAD
                            </span>
                        </div>
                        <input
                            type="range"
                            min={50}
                            max={3000}
                            step={10}
                            value={maxPrice}
                            onChange={(e) => setMaxPrice(e.target.value)}
                            onMouseUp={() => applyFilters()}
                            onTouchEnd={() => applyFilters()}
                            className="w-full accent-terracotta"
                        />
                        <div className="mt-2 flex justify-between text-xs text-ink-soft">
                            <span>50</span>
                            <span>3000</span>
                        </div>
                    </div>

                    {/* Rating */}
                    <div>
                        <p className="eyebrow mb-4">Note minimum</p>
                        <div className="flex gap-1">
                            {[1, 2, 3, 4, 5].map((n) => (
                                <button
                                    key={n}
                                    onClick={() => handleRating(n)}
                                    aria-label={`${n} étoile`}
                                    className="p-1"
                                >
                                    <Star
                                        className={`h-4 w-4 ${n <= minRating ? 'fill-terracotta text-terracotta' : 'text-ink/30'}`}
                                        strokeWidth={1.5}
                                    />
                                </button>
                            ))}
                        </div>
                    </div>

                    <button
                        onClick={resetFilters}
                        className="link-underline text-sm text-ink-soft hover:text-ink"
                    >
                        Réinitialiser les filtres
                    </button>
                </aside>

                {/* Product grid */}
                <div className="col-span-12 md:col-span-9">
                    {products.data.length === 0 ? (
                        <div className="border border-dashed border-ink/20 py-24 text-center text-ink-soft">
                            Aucune pièce ne correspond à vos filtres.
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 md:grid-cols-3 md:gap-6">
                            {products.data.map((p) => {
                                const cover = p.images[0]?.url;
                                const onSale =
                                    p.sale_price &&
                                    Number(p.sale_price) < Number(p.base_price);

                                return (
                                    <Link
                                        key={p.id}
                                        href={`/products/${p.slug}`}
                                        className="group block"
                                    >
                                        <div className="relative aspect-4/5 overflow-hidden bg-ink/5">
                                            {cover ? (
                                                <img
                                                    src={cover}
                                                    alt={p.name}
                                                    loading="lazy"
                                                    className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                                                />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center text-xs text-ink-soft">
                                                    Sans image
                                                </div>
                                            )}
                                            {onSale && (
                                                <span className="absolute top-3 left-3 bg-terracotta px-2 py-1 text-[10px] tracking-[0.18em] text-cream uppercase">
                                                    Promo
                                                </span>
                                            )}
                                            {p.stock === 0 && (
                                                <span className="absolute top-3 right-3 bg-ink/80 px-2 py-1 text-[10px] tracking-[0.18em] text-cream uppercase">
                                                    Épuisé
                                                </span>
                                            )}
                                        </div>

                                        <div className="mt-3">
                                            <p className="text-[11px] tracking-[0.18em] text-ink-soft uppercase">
                                                {p.store.store_name}
                                                {p.category && (
                                                    <>
                                                        {' '}
                                                        ·{' '}
                                                        {
                                                            p.category
                                                                .translated_name
                                                        }
                                                    </>
                                                )}
                                            </p>
                                            <div className="mt-1 flex items-start justify-between gap-3">
                                                <h3 className="font-display text-base leading-tight text-ink transition-colors group-hover:text-terracotta sm:text-lg">
                                                    {p.name}
                                                </h3>
                                                <div className="shrink-0 text-right">
                                                    {onSale ? (
                                                        <>
                                                            <span className="font-display text-base text-terracotta">
                                                                {Number(
                                                                    p.sale_price,
                                                                )}{' '}
                                                                <span className="text-xs">
                                                                    MAD
                                                                </span>
                                                            </span>
                                                            <span className="block text-xs text-ink-soft line-through">
                                                                {Number(
                                                                    p.base_price,
                                                                )}{' '}
                                                                MAD
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <span className="font-display text-base text-ink">
                                                            {Number(
                                                                p.base_price,
                                                            )}{' '}
                                                            <span className="text-xs">
                                                                MAD
                                                            </span>
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            {Number(p.average_rating) > 0 && (
                                                <div className="mt-1.5 flex items-center gap-1 text-xs text-ink-soft">
                                                    <Star
                                                        className="h-3 w-3 fill-terracotta text-terracotta"
                                                        strokeWidth={1.5}
                                                    />
                                                    {p.average_rating}
                                                </div>
                                            )}
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    )}

                    {/* Load more */}
                    <div className="mt-10 flex flex-col items-center gap-3 md:mt-14">
                        {products.next_page_url && (
                            <button
                                onClick={loadMore}
                                className="border border-ink px-8 py-3 text-sm text-ink transition-colors hover:bg-ink hover:text-cream"
                            >
                                Charger plus de pièces
                            </button>
                        )}
                        <p className="text-xs text-ink-soft">
                            {products.data.length} affichées sur{' '}
                            {products.total}
                        </p>
                    </div>
                </div>
            </section>

            <SiteFooter />
        </div>
    );
};

export default Index;
