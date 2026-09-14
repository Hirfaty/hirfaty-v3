import { Head } from '@inertiajs/react';
import ArtisanCta from '@/components/ArtisanCta';
import type { ShowcaseProduct } from '@/components/home/ProductShowcase';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import HeroSection from '@/components/home_page/HeroSection';
import ExploreByCategories from '@/components/home_page/ExploreByCategories';
import { ExploreProducts } from '@/components/home_page/ExploreProducts';
import BrochureSection from '@/components/home_page/BrochureSection';
import OneWorkshop from '@/components/home_page/OneWorkshop';
import ContactSection from '@/components/home_page/ContactSection';

export default function Welcome({
    featuredProducts = [],
}: {
    canRegister?: boolean;
    featuredProducts?: ShowcaseProduct[];
}) {
    return (
        <>
            <Head title="Hirfaty — Moroccan craft, direct from the artisans" />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* ───────────────────────── Hero ───────────────────────── */}
                <HeroSection />

                {/* ─────────────── Explore by categories ─────────────── */}
                <ExploreByCategories />

                {/* ───────────────── Explore our products ───────────────── */}
                <ExploreProducts featuredProducts={featuredProducts} />

                {/* ───────────────────── Product catalogue ───────────────────── */}
                <BrochureSection />

                {/* ───────────── One workshop, two ways to sell ───────────── */}
                <OneWorkshop />

                {/* ───────────────────────── Contact ───────────────────────── */}
                <ContactSection />

                {/* ───────────────────── Gallery ───────────────────── */}

                <div>logos</div>

                <ArtisanCta />

                <SiteFooter />
            </div>
        </>
    );
}
