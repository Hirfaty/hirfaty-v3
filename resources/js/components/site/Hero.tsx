import heroImg from '/images/hero-artisan.jpg';
import { TerracottaBlob, PebbleBlob, ArcRibbon } from './DecorativeShapes';
import { Reveal } from './Reveal';

export function Hero() {
    return (
        <section className="relative flex min-h-[90vh] flex-1 content-center items-center justify-center overflow-hidden">
            {/* decorative background artwork */}
            {/* <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-0"
            >
                <PebbleBlob className="drift-slow absolute bottom-[-22%] -left-[24%] w-[46vw] max-w-[600px] text-secondary/30" />
                <TerracottaBlob className="drift absolute bottom-[-30%] -left-[20%] w-[30vw] max-w-[400px] text-primary/45" />
                <TerracottaBlob className="absolute top-[-8%] -right-[16%] w-[30vw] max-w-[420px] text-butter/55" />
                <ArcRibbon className="absolute right-[8%] bottom-[6%] hidden w-[150px] text-accent/25 lg:block" />
            </div> */}

            {/* <img
                src="images/test3.svg"
                className="absolute bottom-4 left-0 opacity-10 lg:w-xl xl:w-2xl"
            /> */}

            <div className="relative z-10 mx-auto grid min-h-full w-full items-center justify-center gap-12 align-middle sm:px-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-4 lg:px-0 lg:pl-14">
                <Reveal className="relative z-10 my-auto lg:pr-8">
                    <h1 className="display-title text-[clamp(3.4rem,11vw,7.8rem)] text-foreground uppercase">
                        Artful
                        <br />
                        Living
                    </h1>
                    <p className="mt-7 max-w-sm text-[0.95rem] leading-relaxed text-muted-foreground">
                        Objects made slowly, by hand — ceramics, woven cloth and
                        quiet decor from a small circle of makers.
                    </p>
                    <a
                        href="#collections"
                        className="mt-9 inline-flex items-center rounded-full bg-primary px-9 py-4 eyebrow text-primary-foreground transition-all duration-300 hover:bg-bark"
                    >
                        Explore Curated Goods
                    </a>
                </Reveal>

                <Reveal delay={140} className="relative px-2">
                    <div className="relative overflow-hidden lg:rounded-tl-full lg:rounded-bl-full">
                        <img
                            // src={heroImg}
                            src="images/hero-artisan.jpg"
                            alt="Artisan holding a handmade ceramic jug beside dried grasses"
                            width={1104}
                            height={1200}
                            className="h-[380px] w-full object-cover object-center transition-transform duration-[1200ms] ease-out hover:scale-[1.03] sm:h-[480px] lg:h-[500px]"
                        />
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
