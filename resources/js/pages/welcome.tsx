import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Sparkles } from 'lucide-react';
import CountUp from '@/components/CountUp';
import Reveal from '@/components/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/site/SiteHeader';
import WarmFill from '@/components/WarmFill';
import { useTranslation } from '@/hooks/use-translation';

import catAccessories from '@/images/cat-accessories.jpg';
import catBeauty from '@/images/cat-beauty.jpg';
import catClothes from '@/images/cat-clothes.jpg';
import catHome from '@/images/cat-home.jpg';
import catShoes from '@/images/cat-shoes.jpg';
import catTerroir from '@/images/cat-terroir.jpg';
import { Hero } from '@/components/site/Hero';
import { Collections } from '@/components/site/Collections';
import heroMain from '@/images/hero-main.jpg';
import heroSpice from '@/images/hero-spice.jpg';
import heroTextile from '@/images/hero-textile.jpg';
import heroZellige from '@/images/hero-zellige.jpg';
import { Journal } from '@/components/site/Journal';
import { Footer } from '@/components/site/Footer';

const categories = [
    { key: 'clothes', img: catClothes, count: 184 },
    { key: 'beauty', img: catBeauty, count: 96 },
    { key: 'shoes', img: catShoes, count: 142 },
    { key: 'accessories', img: catAccessories, count: 218 },
    { key: 'home', img: catHome, count: 305 },
    { key: 'terroir', img: catTerroir, count: 128 },
];

const craftKeys = [
    'pottery',
    'zellige',
    'weaving',
    'leather',
    'jewelry',
    'spices',
    'wood',
    'embroidery',
];

export default function Welcome() {
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
            <div className="flex min-h-screen flex-col">
                <SiteHeader />
                <Hero />
                <Collections />
                <Journal />
                <Footer />
            </div>
        </>
    );
}
