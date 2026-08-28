import textiles from '@/assets/col-textiles.jpg';
import ceramics from '@/assets/col-ceramics.jpg';
import apparel from '@/assets/col-apparel.jpg';
import decor from '@/assets/col-decor.jpg';
import { HalfDisc, TearDrop } from './DecorativeShapes';
import { Reveal } from './Reveal';

const COLLECTIONS = [
    {
        title: 'Handcrafted Textiles',
        copy: 'Hand-loomed cloth in indigo, flax and undyed cotton.',
        image: 'images/c-1.jpg',
        shape: 'shape-tile-a',
        panel: 'bg-indigo-deep text-primary-foreground',
    },
    {
        title: 'Ceramic Objects',
        copy: 'Wheel-thrown vessels finished in matte earthen glaze.',
        image: 'images/c-2.jpg',
        shape: 'shape-tile-c',
        panel: 'bg-primary text-primary-foreground',
    },
    {
        title: 'Contemporary Apparel',
        copy: 'Relaxed linen silhouettes cut for everyday wear.',
        image: 'images/c-3.jpg',
        shape: 'shape-tile-b',
        panel: 'bg-indigo-deep text-primary-foreground',
    },
    {
        title: 'Modern Decor',
        copy: 'Sculptural pieces for calm, considered interiors.',
        image: 'images/c-4.jpg',
        shape: 'shape-tile-d',
        panel: 'bg-secondary text-foreground',
    },
];

export function Collections() {
    return (
        <section
            id="collections"
            className="relative isolate overflow-hidden py-20 sm:py-28 lg:py-32"
        >
            <img
                src="images/test.svg"
                className="absolute top-0 left-0 w-2xl"
            />
            <img
                src="images/test2.svg"
                className="absolute right-0 bottom-4 w-2xl"
            />
            {/* <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-0"
            >
                <HalfDisc className="absolute top-[4%] -left-[6%] h-[260px] w-[130px] text-primary/45 sm:h-[380px] sm:w-[190px]" />
                <TearDrop className="drift absolute top-[36%] -right-[14%] w-[260px] text-butter/45" />
            </div> */}

            <div className="relative z-10 mx-auto max-w-[1560px] px-5 sm:px-8 lg:px-14">
                <Reveal>
                    <h2 className="text-center display-title text-[clamp(2rem,5vw,3.4rem)] text-foreground uppercase">
                        The Collections
                    </h2>
                </Reveal>

                <ul className="mt-14 grid gap-7 sm:grid-cols-2 lg:mt-20 lg:grid-cols-4 lg:gap-8">
                    {COLLECTIONS.map((c, i) => (
                        <Reveal as="li" key={c.title} delay={i * 110}>
                            <article
                                className={`group ${c.shape} overflow-hidden`}
                            >
                                <div className={`overflow-hidden ${c.panel}`}>
                                    <img
                                        src={c.image}
                                        alt={c.title}
                                        width={800}
                                        height={640}
                                        loading="lazy"
                                        className="h-[210px] w-full rounded-br-[80px] object-cover transition-transform duration-700 ease-out group-hover:scale-105 lg:h-[240px]"
                                    />
                                </div>
                                <div className={`px-6 pt-6 pb-7 ${c.panel}`}>
                                    <h3 className="font-display text-[1.35rem] leading-snug">
                                        {c.title}
                                    </h3>
                                    <p className="mt-2 text-[0.8rem] leading-relaxed opacity-80">
                                        {c.copy}
                                    </p>
                                    <a
                                        href="#"
                                        className="mt-5 inline-block border-b border-current pb-1 eyebrow opacity-90 transition-opacity hover:opacity-100"
                                    >
                                        Shop Now
                                    </a>
                                </div>
                            </article>
                        </Reveal>
                    ))}
                </ul>
            </div>
        </section>
    );
}
