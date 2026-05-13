import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import WarmFill from '@/components/WarmFill';
import { useTranslation } from '@/hooks/use-translation';

import catAccessories from '@/images/cat-accessories.jpg';
import catBeauty from '@/images/cat-beauty.jpg';
import catClothes from '@/images/cat-clothes.jpg';
import catHome from '@/images/cat-home.jpg';
import catShoes from '@/images/cat-shoes.jpg';
import catTerroir from '@/images/cat-terroir.jpg';
import heroMain from '@/images/hero-main.jpg';
import heroSpice from '@/images/hero-spice.jpg';
import heroTextile from '@/images/hero-textile.jpg';
import heroZellige from '@/images/hero-zellige.jpg';

const categories = [
    { key: 'clothes', img: catClothes, count: 184 },
    { key: 'beauty', img: catBeauty, count: 96 },
    { key: 'shoes', img: catShoes, count: 142 },
    { key: 'accessories', img: catAccessories, count: 218 },
    { key: 'home', img: catHome, count: 305 },
    { key: 'terroir', img: catTerroir, count: 128 },
];

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('welcome.hero_eyebrow')}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600"
                    rel="stylesheet"
                />
            </Head>
            <div className="zellie-bg min-h-screen">
                <SiteHeader />
                {/* HERO */}
                <section className="container pt-14 pb-20 md:pt-24 md:pb-28">
                    <div className="grid grid-cols-12 items-end gap-6 md:gap-10">
                        <div className="col-span-12 md:col-span-7">
                            <p className="eyebrow mb-6">
                                {t('welcome.hero_eyebrow')}
                            </p>
                            <h1 className="h-editorial text-[3rem] text-ink sm:text-[4.5rem] md:text-[6rem]">
                                {t('welcome.hero_title')}
                                <br />
                                {t('welcome.hero_subtitle')}
                                <span className="block font-normal text-terracotta italic">
                                    {t('welcome.hero_emphasis')}
                                </span>
                                <span className="block">
                                    {t('welcome.hero_cta')}
                                </span>
                            </h1>

                            <div className="mt-10 flex flex-col gap-5 sm:flex-row sm:items-center sm:gap-8">
                                <Link
                                    href="/products"
                                    className="group inline-flex w-fit items-center gap-3 bg-ink px-6 py-3.5 text-sm text-cream transition-colors hover:bg-terracotta"
                                >
                                    {t('welcome.market_button')}
                                    <ArrowUpRight className="h-4 w-4 transition-transform group-hover:rotate-45" />
                                </Link>
                                <Link
                                    href="/register?type=seller"
                                    className="link-underline w-fit text-sm text-ink-soft hover:text-ink"
                                >
                                    {t('welcome.artisan_cta')}
                                </Link>
                            </div>
                        </div>

                        {/* Offset visual stack — real photography */}
                        <div className="col-span-12 grid h-[420px] grid-cols-6 grid-rows-6 gap-3 md:col-span-5 md:h-[520px]">
                            <img
                                src={heroMain}
                                alt="Mains d'artisan façonnant la poterie"
                                width={1024}
                                height={1280}
                                className="col-span-4 row-span-4 h-full w-full object-cover"
                            />
                            <img
                                src={heroZellige}
                                alt="Motif zellige marocain"
                                width={768}
                                height={768}
                                loading="lazy"
                                className="col-span-2 row-span-2 h-full w-full object-cover"
                            />
                            <img
                                src={heroTextile}
                                alt="Textile tissé main"
                                width={768}
                                height={768}
                                loading="lazy"
                                className="col-span-2 row-span-2 h-full w-full object-cover"
                            />
                            <WarmFill
                                variant="ink"
                                className="col-span-3 row-span-2 flex items-end p-4"
                            >
                                <p className="font-arabic text-2xl leading-none text-cream">
                                    حِرفتي
                                </p>
                            </WarmFill>
                            <img
                                src={heroSpice}
                                alt="Épices marocaines"
                                width={768}
                                height={768}
                                loading="lazy"
                                className="col-span-3 row-span-2 h-full w-full object-cover"
                            />
                        </div>
                    </div>

                    {/* Stats strip */}
                    <div className="mt-16 grid grid-cols-2 gap-x-10 gap-y-6 border-t border-ink/15 pt-6 text-sm md:mt-24 md:grid-cols-4">
                        {[
                            [
                                t('welcome.stats_coming_soon'),
                                t('welcome.stats_first_shops'),
                            ],
                            ['41', t('welcome.stats_cities')],
                            ['6', t('welcome.stats_trades')],
                            ['100%', t('welcome.stats_verified')],
                        ].map(([n, l]) => (
                            <div key={l}>
                                <p className="font-display text-3xl leading-none text-ink">
                                    {n}
                                </p>
                                <p className="mt-2 text-ink-soft">{l}</p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Quiet zellige divider strip */}
                {/* <div className="zelligestars h-10 border-y border-ink/10 opacity-80" /> */}

                {/* CATEGORIES */}
                <section className="container py-20 md:py-28">
                    <div className="mb-10 flex items-end justify-between gap-6">
                        <div>
                            <p className="eyebrow mb-3">
                                {t('welcome.categories_section')}
                            </p>
                            <h2 className="h-editorial max-w-xl text-4xl text-ink md:text-5xl">
                                {t('welcome.categories_heading')}
                            </h2>
                        </div>
                        <Link
                            href="/products"
                            className="link-underline hidden text-sm text-ink-soft hover:text-ink md:inline-flex"
                        >
                            {t('welcome.see_market')}
                        </Link>
                    </div>

                    <div className="grid auto-rows-[140px] grid-cols-12 gap-3 md:auto-rows-[180px] md:gap-4">
                        {categories.map((c, i) => {
                            const sizes = [
                                'col-span-7 row-span-2',
                                'col-span-5 row-span-1',
                                'col-span-5 row-span-2',
                                'col-span-4 row-span-1',
                                'col-span-3 row-span-1',
                                'col-span-12 md:col-span-12 row-span-1',
                            ];

                            return (
                                <Link
                                    key={c.key}
                                    href="/products"
                                    className={`group relative overflow-hidden ${sizes[i]} block`}
                                >
                                    <img
                                        src={c.img}
                                        alt={t(
                                            `welcome.categories.${c.key}.name`,
                                        )}
                                        loading="lazy"
                                        width={1024}
                                        height={1024}
                                        className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.05]"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-tr from-ink/65 via-ink/15 to-transparent" />
                                    <div className="relative flex h-full flex-col justify-between p-5 text-cream md:p-7">
                                        <p className="font-arabic text-lg opacity-85">
                                            {t(
                                                `welcome.categories.${c.key}.ar`,
                                            )}
                                        </p>
                                        <div className="flex items-end justify-between">
                                            <h3 className="font-display text-2xl leading-none md:text-4xl">
                                                {t(
                                                    `welcome.categories.${c.key}.name`,
                                                )}
                                            </h3>
                                            <span className="text-xs tracking-[0.18em] uppercase opacity-85">
                                                {c.count} {t('welcome.pieces')}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </section>

                {/* HOW IT WORKS */}
                <section className="container py-24 md:py-32">
                    <p className="eyebrow mb-8">
                        {t('welcome.how_it_works_section')}
                    </p>
                    <div className="space-y-1 md:space-y-2">
                        {[
                            [
                                '01',
                                t('welcome.how_it_works.step1.title'),
                                t('welcome.how_it_works.step1.description'),
                            ],
                            [
                                '02',
                                t('welcome.how_it_works.step2.title'),
                                t('welcome.how_it_works.step2.description'),
                            ],
                            [
                                '03',
                                t('welcome.how_it_works.step3.title'),
                                t('welcome.how_it_works.step3.description'),
                            ],
                        ].map(([n, tText, d]) => (
                            <div
                                key={n}
                                className="grid grid-cols-12 gap-4 border-t border-ink/15 py-5 md:gap-8 md:py-7"
                            >
                                <div className="col-span-2 pt-2 font-display text-xl text-ink-soft md:col-span-1">
                                    {n}
                                </div>
                                <h3 className="h-editorial col-span-10 text-3xl text-ink md:col-span-5 md:text-5xl">
                                    {tText}
                                </h3>
                                <p className="col-span-12 max-w-lg self-center text-base leading-relaxed text-ink-soft md:col-span-6 md:text-lg">
                                    {d}
                                </p>
                            </div>
                        ))}
                        <div className="border-t border-ink/15" />
                    </div>
                </section>

                {/* SELLER CTA */}
                <section className="mt-12">
                    <div className="bg-terracotta text-cream">
                        <div className="container grid grid-cols-12 items-center gap-6 py-20 md:gap-10 md:py-28">
                            <div className="col-span-12 md:col-span-7">
                                <p
                                    className="mb-4 font-arabic text-2xl opacity-95 md:text-4xl"
                                    dir="rtl"
                                >
                                    {t('welcome.seller_cta.ar_line')}
                                </p>
                                <h2 className="h-editorial text-4xl md:text-6xl">
                                    {t('welcome.seller_cta.title')}
                                    <br />
                                    <span className="italic">
                                        {t('welcome.seller_cta.subtitle')}
                                    </span>
                                </h2>
                                <p className="mt-6 max-w-xl leading-relaxed text-cream/85">
                                    {t('welcome.seller_cta.description')}
                                </p>
                            </div>
                            <div className="col-span-12 md:col-span-5 md:text-right">
                                <Link
                                    href="/register?type=seller"
                                    className="inline-flex items-center gap-3 bg-cream px-7 py-4 text-ink transition-colors hover:bg-ink hover:text-cream"
                                >
                                    {t('welcome.seller_cta.button')}
                                    <ArrowUpRight className="h-4 w-4" />
                                </Link>
                                <p className="mt-4 text-xs tracking-[0.18em] text-cream/70 uppercase">
                                    {t('welcome.seller_cta.note')}
                                </p>
                            </div>
                        </div>
                        <div
                            className="zellige-divider"
                            style={{ filter: 'invert(1)', opacity: 0.25 }}
                        />
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
