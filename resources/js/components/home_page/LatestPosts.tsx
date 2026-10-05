import { Link } from '@inertiajs/react';
import { ArrowRight, ImageOff } from 'lucide-react';
import Reveal from '@/components/home/Reveal';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/utils';
import type { Blog } from '@/types';

/** Museum-caption label, matching the journal listing's eyebrow. */
const EYEBROW = 'text-[10px] font-semibold uppercase tracking-[0.16em]';

/**
 * "The Journal": the three newest articles, below the brochure.
 *
 * Fed by `HomeService::latestPosts()`, which only returns published posts.
 * Like the new-arrivals row, the section simply is not there until something
 * has been written.
 */
export default function LatestPosts({ posts = [] }: { posts?: Blog[] }) {
    const { t } = useTranslation();

    if (posts.length === 0) {
        return null;
    }

    return (
        <section className="bg-souk-paper py-10 lg:py-14">
            <div className="container">
                <Reveal delay={80}>
                    <h2 className="text-center font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                        {t('welcome.blog.heading')}
                    </h2>
                </Reveal>

                <Reveal delay={160} className="mt-6 lg:mt-8">
                    <Link
                        href={route('blog.index')}
                        className="group inline-flex items-center gap-2 border-b border-souk-brown/40 pb-1 text-sm text-souk-brown transition-colors hover:border-souk-brown"
                    >
                        {t('welcome.blog.view_all')}
                        <ArrowRight
                            className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                            strokeWidth={1.75}
                        />
                    </Link>

                    <ul className="mt-6 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.map((post) => (
                            <li key={post.id}>
                                <PostCard post={post} />
                            </li>
                        ))}
                    </ul>
                </Reveal>
            </div>
        </section>
    );
}

function PostCard({ post }: { post: Blog }) {
    const { t, locale } = useTranslation();

    return (
        <Link href={route('blog.show', post.slug)} className="group block">
            <div className="aspect-4/3 overflow-hidden rounded-md bg-souk-peach">
                {post.cover_url ? (
                    <img
                        src={post.cover_url}
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

            <p className={`${EYEBROW} mt-4 text-souk-brown/70`}>
                {post.published_at &&
                    formatDate(post.published_at, `${locale}-MA`)}
                {' · '}
                {t('welcome.blog.minutes').replace(
                    ':count',
                    String(post.reading_minutes),
                )}
            </p>

            <h3 className="mt-2 font-display text-xl text-souk-title transition-colors group-hover:text-souk-brown">
                {post.title}
            </h3>

            {post.excerpt && (
                <p className="mt-2 line-clamp-3 text-sm text-souk-brown-deep">
                    {post.excerpt}
                </p>
            )}

            <span className="mt-4 inline-block border-b border-souk-brown pb-0.5 text-xs tracking-[0.16em] text-souk-brown uppercase">
                {t('welcome.blog.read')}
            </span>
        </Link>
    );
}
