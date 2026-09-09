import { useSlideshow } from '@/hooks/use-slideshow';

export type BackdropSlide = {
    src: string;
    /** Already-translated alt text — this component does no lookups itself. */
    alt: string;
};

/**
 * The home hero's photography, as a crossfading stack behind the copy.
 *
 * Distinct from `HeroCarousel`, which is a split card with the type on a flat
 * panel beside the picture. Here the copy sits *over* the photograph, so the
 * stack is absolutely positioned behind it and carries the scrim itself —
 * that keeps the two gradients travelling with the images rather than being
 * repeated at every call site.
 *
 * Crossfaded rather than translated, so nothing has to know about RTL: a
 * sliding track would need its direction flipped for Arabic.
 *
 * The dots are `absolute` at the foot of the section rather than in flow,
 * because the hero sizes itself from the copy on top of this.
 */
export default function HeroBackdrop({
    slides,
    interval = 3000,
    label,
}: {
    slides: BackdropSlide[];
    /** Milliseconds each slide stays before advancing. */
    interval?: number;
    /** Accessible name for the dot group, e.g. "Choose a photograph". */
    label: string;
}) {
    const { index, previous, goTo, reduced, pauseProps } = useSlideshow(
        slides.length,
        interval,
    );

    if (slides.length === 0) {
        return null;
    }

    return (
        <>
            {/* `isolate` is load-bearing: without a stacking context of its
                own the layers' z-indices would compete with the scrims below,
                which sit at `z-index: auto`, and the photographs would paint
                straight over the wash that keeps the copy legible. */}
            <div aria-hidden="true" className="absolute inset-0 isolate">
                {slides.map((slide, i) => {
                    const active = i === index;
                    // The slide being replaced stays fully opaque *underneath*
                    // the incoming one. Without it a plain crossfade passes
                    // through a moment where both layers are half-transparent,
                    // and the page darkens visibly at the midpoint — the dip
                    // is what makes a naive dissolve look cheap.
                    const outgoing = i === previous && i !== index;

                    return (
                        <div
                            key={slide.src}
                            className={`absolute inset-0 ${
                                // Three explicit layers rather than DOM order.
                                // A slide two steps back drops to transparent
                                // instantly, and if it happened to sit later in
                                // the DOM than the outgoing one it would paint
                                // above it — so clicking the dots quickly cut
                                // to the wrong picture for a frame behind the
                                // fade. Ranking the layers removes the case.
                                active
                                    ? 'z-20 opacity-100'
                                    : outgoing
                                      ? 'z-10 opacity-100'
                                      : 'z-0 opacity-0'
                            } ${
                                // Only the incoming layer animates; the one
                                // underneath is swapped instantly, hidden
                                // behind it. Reduced motion cuts straight.
                                // Linear, not eased: because the layer
                                // underneath stays opaque, opacity here maps
                                // straight onto how much of the new picture
                                // you see. An eased curve spends most of the
                                // window already arrived, which reads as a
                                // quick swap followed by a pause rather than
                                // as a dissolve.
                                active && !reduced
                                    ? 'transition-opacity duration-[1400ms] ease-linear'
                                    : ''
                            }`}
                        >
                            <img
                                src={slide.src}
                                alt=""
                                // The first slide is the LCP candidate; the
                                // rest are invisible until their turn.
                                loading={i === 0 ? 'eager' : 'lazy'}
                                className="h-full w-full object-cover"
                            />
                        </div>
                    );
                })}
            </div>

            {/*
             * The scrim, over the whole stack rather than per slide — a flat
             * wash plus a *vertical* gradient rather than the side-weighted
             * falloff the design draws: a horizontal gradient is a physical
             * direction, not a logical one, so under `dir="rtl"` the copy
             * swings to the right while the dark end stays left and the Arabic
             * ends up on the bright half of the photograph.
             */}
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-souk-title/65"
            />
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-gradient-to-t from-souk-title/80 via-souk-title/30 to-souk-title/55"
            />

            {slides.length > 1 && (
                <div
                    {...pauseProps}
                    role="group"
                    aria-label={label}
                    className="absolute inset-x-0 bottom-7 z-10 flex items-center justify-center gap-2.5 lg:bottom-10"
                >
                    {slides.map((slide, i) => {
                        const active = i === index;

                        return (
                            <button
                                key={slide.src}
                                type="button"
                                onClick={() => goTo(i)}
                                aria-label={slide.alt}
                                aria-current={active}
                                className={`h-1.5 rounded-full transition-all duration-500 ${
                                    active
                                        ? 'w-8 bg-souk-cream'
                                        : 'w-1.5 bg-white/40 hover:bg-white/70'
                                }`}
                            />
                        );
                    })}
                </div>
            )}
        </>
    );
}
