import { Link } from '@inertiajs/react';
import exploreArtVivre from '@/images/categories/art-vivre.jpeg';
import exploreLantern from '@/images/categories/lantern.jpeg';
import explorePottery from '@/images/categories/pottery.jpeg';
import exploreTextile from '@/images/categories/textile.jpeg';
import exploreWood from '@/images/categories/wood.jpeg';

/**
 * The tiles, back to front-of-array so the last one sits highest in the DOM
 * (and therefore visually in front where two overlap).
 *
 * `y` places each tile along a shallow arc — negative lifts it, positive
 * drops it — and `rotate` gives it a slight independent tilt, together
 * reading as a loosely fanned handful of pieces rather than a straight,
 * static row. `delay` staggers each tile's orbit (see the `orbit` keyframes
 * in app.css) so the five drift out of phase with each other instead of
 * bobbing in lockstep.
 *
 * Placeholder photography — the category shots already in the bundle, not
 * product cutouts — swap for real product photos when they're available; the
 * arc/orbit treatment doesn't care what's inside the tile.
 */
const TILES = [
    { src: explorePottery, y: 18, rotate: -9, delay: '0s' },
    { src: exploreTextile, y: -4, rotate: -4, delay: '-1.4s' },
    { src: exploreLantern, y: -16, rotate: 3, delay: '-2.8s' },
    { src: exploreArtVivre, y: -6, rotate: 7, delay: '-4.2s' },
    { src: exploreWood, y: 12, rotate: 10, delay: '-5.6s' },
] as const;

/**
 * The "nothing here yet, go and look" card — shared by the buyer's empty
 * order list and empty wishlist.
 *
 * A white card rather than the quiet icon-and-line `Placeholder` the
 * unfinished sections use: an empty list on a working feature is not a missing
 * page, it's an invitation, and it should read as a small piece of marketing
 * rather than an apology.
 *
 * Copy is passed in because only the words differ between the two — the
 * layout, the arc, the motion and the crop are identical, and two copies of
 * this file would drift the moment one of them was nudged.
 *
 * The tiles drift slowly along their arc (`animate-orbit`, behind
 * `motion-safe` so it drops out entirely under prefers-reduced-motion) —
 * enough motion to catch the eye without asking it to track anything.
 */
export default function EmptyShowcase({
    eyebrow,
    title,
    ctaLabel,
    ctaHref = route('products.index'),
}: {
    eyebrow: string;
    title: string;
    ctaLabel: string;
    ctaHref?: string;
}) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white p-6 shadow-sm shadow-souk-brown/5 sm:p-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-base text-souk-brown-deep/55">
                        {eyebrow}
                    </p>
                    <p className="mt-1 font-display text-3xl text-souk-title">
                        {title}
                    </p>
                </div>

                <Link
                    href={ctaHref}
                    className="inline-flex shrink-0 items-center rounded-full bg-souk-green px-5 py-2.5 text-base font-medium text-white transition-colors hover:bg-souk-green-deep"
                >
                    {ctaLabel}
                </Link>
            </div>

            {/* Bleeds past the card's own edges on the left — `-ms-10`
                against the card's `p-6`/`p-8` lets the first tile run under
                the rounded corner and get clipped by the card's own
                `overflow-hidden`, the same partial-crop the reference uses to
                suggest the row continues past what's shown. */}
            <div
                aria-hidden="true"
                className="-ms-10 mt-10 flex items-end justify-center gap-3 sm:mt-12 sm:gap-4"
            >
                {TILES.map((tile, i) => (
                    <div
                        key={i}
                        style={{
                            transform: `translateY(${tile.y}px) rotate(${tile.rotate}deg)`,
                        }}
                        className="shrink-0"
                    >
                        {/* Only this inner element animates — the outer div
                            above holds the tile's static arc position, so the
                            keyframes (a plain translate) don't have to also
                            encode it. */}
                        <div
                            style={{ animationDelay: tile.delay }}
                            className="motion-safe:animate-orbit"
                        >
                            <span className="block h-20 w-20 overflow-hidden rounded-2xl bg-souk-peach shadow-sm sm:h-24 sm:w-24 md:h-28 md:w-28">
                                <img
                                    src={tile.src}
                                    alt=""
                                    loading="lazy"
                                    className="h-full w-full object-cover"
                                />
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
