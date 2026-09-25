import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ImageOff, PenLine } from 'lucide-react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { formatDate } from '@/lib/utils';
import type { Blog, PageProps } from '@/types';

const EYEBROW = 'text-[10px] font-semibold uppercase tracking-[0.16em]';

/**
 * What the preview banner calls the article's state. Not the bare `status`
 * string: "published" on a post dated into the future would read as live when
 * it is only queued, which is exactly what the preview exists to catch.
 */
function stateLabel(blog: Blog): string {
    if (blog.is_published) {
        return 'article en ligne';
    }

    if (blog.status === 'published') {
        return 'programmé, pas encore visible';
    }

    return blog.status === 'archived' ? 'archivé' : 'brouillon';
}

/**
 * A single article.
 *
 * Rendered by both the public route and the admin preview — that is deliberate.
 * The preview is only trustworthy if it is literally the same component, so the
 * only thing `preview` changes is the banner at the top.
 */
export default function BlogShow({
    blog,
    related,
    preview,
}: PageProps & {
    blog: Blog;
    related: Blog[];
    preview: boolean;
}) {
    return (
        <>
            {/* Description, Open Graph and structured data are rendered
                server-side (SeoService) — a client-side copy here would
                duplicate them once the page hydrates. */}
            <Head title={blog.title} />

            <div className="min-h-screen bg-souk-paper">
                {preview && <PreviewBanner blog={blog} />}

                <SiteHeader />

                <article className="container py-12 md:py-16">
                    <div className="mx-auto max-w-3xl">
                        <Link
                            href={route('blog.index')}
                            className="inline-flex items-center gap-1.5 text-xs tracking-[0.16em] text-souk-brown/70 uppercase transition-colors hover:text-souk-brown"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Le Journal
                        </Link>

                        <header className="mt-6">
                            <p className={`${EYEBROW} text-souk-brown/70`}>
                                {blog.published_at
                                    ? formatDate(blog.published_at)
                                    : 'Non publié'}
                                {' · '}
                                {blog.reading_minutes} min de lecture
                            </p>

                            <h1 className="mt-3 font-display text-4xl leading-tight text-souk-title md:text-5xl">
                                {blog.title}
                            </h1>

                            {blog.excerpt && (
                                <p className="mt-5 text-lg text-souk-brown-deep italic">
                                    {blog.excerpt}
                                </p>
                            )}

                            {blog.author && (
                                <p className="mt-6 flex items-center gap-2 text-sm text-souk-brown-deep">
                                    <PenLine className="h-3.5 w-3.5 text-souk-brown/60" />
                                    Par {blog.author.name}
                                </p>
                            )}
                        </header>

                        {blog.cover_url && (
                            <img
                                src={blog.cover_url}
                                alt=""
                                fetchPriority="high"
                                className="mt-10 aspect-16/9 w-full rounded-2xl object-cover"
                            />
                        )}

                        {/*
                         * The body is HTML from the editor. It is run through
                         * App\Services\HtmlSanitizer on every write, so what
                         * lands here is an allowlisted subset of tags — see
                         * that class before widening anything.
                         */}
                        {blog.content ? (
                            <div
                                className="blog-article mt-12"
                                dangerouslySetInnerHTML={{
                                    __html: blog.content,
                                }}
                            />
                        ) : (
                            <p className="mt-12 text-souk-brown-deep italic">
                                Cet article n’a pas encore de contenu.
                            </p>
                        )}
                    </div>
                </article>

                {related.length > 0 && (
                    <section className="border-t border-sand/60 bg-souk-peach/30">
                        <div className="container py-14 md:py-20">
                            <h2 className="font-display text-2xl text-souk-title md:text-3xl">
                                À lire <span className="italic">aussi</span>
                            </h2>

                            <div className="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                                {related.map((post) => (
                                    <Link
                                        key={post.id}
                                        href={route('blog.show', post.slug)}
                                        className="group block"
                                    >
                                        <div className="aspect-4/3 overflow-hidden rounded-xl bg-souk-peach">
                                            {post.cover_url ? (
                                                <img
                                                    src={post.cover_url}
                                                    alt=""
                                                    loading="lazy"
                                                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center text-souk-brown/25">
                                                    <ImageOff className="h-6 w-6" />
                                                </div>
                                            )}
                                        </div>
                                        <p
                                            className={`mt-3 ${EYEBROW} text-souk-brown/70`}
                                        >
                                            {post.published_at &&
                                                formatDate(post.published_at)}
                                        </p>
                                        <h3 className="mt-1 font-display text-lg text-souk-title transition-colors group-hover:text-souk-brown">
                                            {post.title}
                                        </h3>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                <SiteFooter />
            </div>
        </>
    );
}

/**
 * Sticky bar telling the admin they are looking at an unpublished draft, so a
 * preview is never mistaken for the live article.
 */
function PreviewBanner({ blog }: { blog: Blog }) {
    return (
        <div className="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-souk-title px-4 py-2.5 text-center text-xs text-souk-cream">
            <span className="tracking-[0.16em] uppercase">
                Aperçu — {stateLabel(blog)}
            </span>
            <Link
                href={route('admin.blogs.edit', blog.id)}
                className="border-b border-souk-cream/50 pb-0.5 transition-colors hover:border-souk-cream"
            >
                Revenir à l’édition
            </Link>
        </div>
    );
}
