// import mainImg from '@/assets/journal-main.jpg';
// import thumbImg from '@/assets/journal-thumb.jpg';
import { TerracottaBlob } from './DecorativeShapes';
import { Reveal } from './Reveal';

export function Journal() {
    return (
        <section className="relative isolate overflow-hidden py-20 sm:py-24 lg:py-32">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-0"
            >
                <TerracottaBlob className="drift absolute -right-[22%] bottom-[-16%] w-[38vw] max-w-[480px] text-secondary/28" />
            </div>

            <div className="relative z-10 mx-auto max-w-[1560px] px-5 sm:px-8 lg:px-14">
                <Reveal>
                    <h2 className="text-center display-title text-[clamp(2rem,5vw,3.4rem)] text-foreground uppercase">
                        Journal Stories
                    </h2>
                </Reveal>

                <Reveal delay={120} className="mt-14 lg:mt-20">
                    <article className="group grid overflow-hidden rounded-tl-[144px] rounded-tr-[144px] lg:grid-cols-[minmax(0,58fr)_minmax(0,42fr)]">
                        <img
                            src="images/journal-main.jpg"
                            alt="Weaver working indigo threads at a wooden loom"
                            width={1200}
                            height={800}
                            loading="lazy"
                            className="h-[260px] w-full object-cover transition-transform duration-[1100ms] ease-out group-hover:scale-[1.04] sm:h-[340px] lg:h-[400px]"
                        />
                        <div className="flex items-center gap-6 bg-primary px-7 py-9 text-primary-foreground sm:px-10 lg:py-10">
                            <img
                                src="images/journal-thumb.jpg"
                                alt="Hands weaving colored threads"
                                width={640}
                                height={640}
                                loading="lazy"
                                className="hidden h-[132px] w-[132px] shrink-0 rounded-lg object-cover sm:block"
                            />
                            <div className="min-w-0">
                                <p className="eyebrow opacity-70">
                                    Design &amp; Community
                                </p>
                                <h3 className="mt-3 font-display text-[1.6rem] leading-tight sm:text-[1.9rem]">
                                    Honoring the Makers
                                </h3>
                                <p className="mt-3 text-[0.83rem] leading-relaxed opacity-80">
                                    A visit to the workshops where our textiles
                                    begin — three generations, one loom, and the
                                    slow rhythm of cloth taking shape.
                                </p>
                                <a
                                    href="#"
                                    className="mt-5 inline-block border-b border-current pb-1 eyebrow transition-opacity hover:opacity-80"
                                >
                                    + Read More
                                </a>
                            </div>
                        </div>
                    </article>
                </Reveal>
            </div>
        </section>
    );
}
