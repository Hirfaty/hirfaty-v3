import { Head, Link, router } from '@inertiajs/react';
import { ImageOff, Search } from 'lucide-react';
import { useState } from 'react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { formatDate } from '@/lib/utils';
import type { Blog, PageProps, PaginatedData } from '@/types';

/** Museum-caption label, matching the product listing's eyebrow. */
const EYEBROW = 'text-[10px] font-semibold uppercase tracking-[0.16em]';

export default function BlogIndex({
    blogs,
    filters,
}: PageProps & {
    blogs: PaginatedData<Blog>;
    filters: { search: string };
}) {
    const [search, setSearch] = useState(filters.search);

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            route('blog.index'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    // The newest post gets the full-width treatment; the rest sit in the grid
    // below it. Only on page 1 — page 2 has no "latest".
    const [lead, ...rest] =
        blogs.current_page === 1 ? blogs.data : [undefined, ...blogs.data];

    return (
        <>
            <Head title="Le Journal — Hirfaty" />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                <header className="border-b border-sand/60 bg-souk-peach/40">
                    <div className="container py-16 md:py-20">
                        <p className={`${EYEBROW} text-souk-brown/70`}>
                            Hirfaty
                        </p>
                        <h1 className="mt-3 font-display text-4xl text-souk-title md:text-6xl">
                            Le <span className="italic">Journal</span>
                        </h1>
                        <p className="mt-4 max-w-xl text-souk-brown-deep">
                            Les gestes, les ateliers et les histoires derrière
                            les pièces que vous trouvez sur Hirfaty.
                        </p>

                        <form
                            onSubmit={submitSearch}
                            className="relative mt-8 max-w-md"
                        >
                            <Search className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-souk-brown/50" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Rechercher un article…"
                                aria-label="Rechercher un article"
                                className="w-full rounded-full border border-sand bg-white py-3 pr-4 pl-11 text-sm text-souk-brown-deep focus:border-souk-brown focus:outline-none"
                            />
                        </form>
                    </div>
                </header>

                <main className="container py-14 md:py-20">
                    {blogs.data.length === 0 ? (
                        <p className="py-20 text-center text-souk-brown-deep">
                            {filters.search
                                ? `Aucun article pour « ${filters.search} ».`
                                : 'Le journal ouvre bientôt ses pages.'}
                        </p>
                    ) : (
                        <>
                            {lead && <LeadCard blog={lead} />}

                            <div className="mt-14 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                                {rest
                                    .filter((b): b is Blog => b !== undefined)
                                    .map((blog) => (
                                        <ArticleCard
                                            key={blog.id}
                                            blog={blog}
                                        />
                                    ))}
                            </div>
                        </>
                    )}

                    {blogs.last_page > 1 && (
                        <nav
                            aria-label="Pagination"
                            className="mt-16 flex items-center justify-center gap-1.5"
                        >
                            {blogs.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    className={`flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm transition ${
                                        link.active
                                            ? 'bg-souk-brown text-souk-cream'
                                            : 'text-souk-brown-deep hover:bg-souk-peach'
                                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </nav>
                    )}
                </main>

                <SiteFooter />
            </div>
        </>
    );
}

/** The most recent article, given a wide two-column layout. */
function LeadCard({ blog }: { blog: Blog }) {
    return (
        <Link
            href={route('blog.show', blog.slug)}
            className="group grid items-center gap-8 md:grid-cols-2 md:gap-12"
        >
            <Cover blog={blog} className="aspect-4/3 rounded-2xl" />

            <div>
                <Meta blog={blog} />
                <h2 className="mt-3 font-display text-3xl text-souk-title transition-colors group-hover:text-souk-brown md:text-4xl">
                    {blog.title}
                </h2>
                {blog.excerpt && (
                    <p className="mt-4 text-souk-brown-deep">{blog.excerpt}</p>
                )}
                <span className="mt-6 inline-block border-b border-souk-brown pb-0.5 text-xs tracking-[0.16em] text-souk-brown uppercase">
                    Lire l’article
                </span>
            </div>
        </Link>
    );
}

function ArticleCard({ blog }: { blog: Blog }) {
    return (
        <Link href={route('blog.show', blog.slug)} className="group block">
            <Cover blog={blog} className="aspect-4/3 rounded-xl" />

            <div className="mt-4">
                <Meta blog={blog} />
                <h3 className="mt-2 font-display text-xl text-souk-title transition-colors group-hover:text-souk-brown">
                    {blog.title}
                </h3>
                {blog.excerpt && (
                    <p className="mt-2 line-clamp-3 text-sm text-souk-brown-deep">
                        {blog.excerpt}
                    </p>
                )}
            </div>
        </Link>
    );
}

function Cover({ blog, className }: { blog: Blog; className: string }) {
    return (
        <div className={`overflow-hidden bg-souk-peach ${className}`}>
            {blog.cover_url ? (
                <img
                    src={blog.cover_url}
                    alt=""
                    loading="lazy"
                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
            ) : (
                <div className="flex h-full w-full items-center justify-center text-souk-brown/25">
                    <ImageOff className="h-7 w-7" />
                </div>
            )}
        </div>
    );
}

function Meta({ blog }: { blog: Blog }) {
    return (
        <p className={`${EYEBROW} text-souk-brown/70`}>
            {blog.published_at && formatDate(blog.published_at)}
            {' · '}
            {blog.reading_minutes} min
        </p>
    );
}
