import { Head } from '@inertiajs/react';
import ArtisanCta from '@/components/ArtisanCta';
import type { ShowcaseProduct } from '@/components/home/ProductShowcase';
import BrochureSection from '@/components/home_page/BrochureSection';
import ContactSection from '@/components/home_page/ContactSection';
import ExploreByCategories from '@/components/home_page/ExploreByCategories';
import { ExploreProducts } from '@/components/home_page/ExploreProducts';
import HeroSection from '@/components/home_page/HeroSection';
import LatestPosts from '@/components/home_page/LatestPosts';
import NewArrivals from '@/components/home_page/NewArrivals';
import OneWorkshop from '@/components/home_page/OneWorkshop';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import type { Blog } from '@/types';

export default function Welcome({
    featuredProducts = [],
    newArrivals = [],
    latestPosts = [],
}: {
    canRegister?: boolean;
    featuredProducts?: ShowcaseProduct[];
    /** The twelve most recently listed pieces — see `HomeController`. */
    newArrivals?: ShowcaseProduct[];
    /** The three newest journal articles — see `HomeService::latestPosts()`. */
    latestPosts?: Blog[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('titles.home')} />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* ───────────────────────── Hero ───────────────────────── */}
                <HeroSection />

                {/* ─────────────── Explore by categories ─────────────── */}
                <ExploreByCategories />

                {/* ───────────────── Explore our products ───────────────── */}
                <ExploreProducts featuredProducts={featuredProducts} />

                {/* ───────────────────── New arrivals ───────────────────── */}
                <NewArrivals products={newArrivals} />

                {/* ───────────────────────── Brochure ───────────────────────── */}
                <BrochureSection />

                {/* ───────────────────────── Journal ───────────────────────── */}
                <LatestPosts posts={latestPosts} />

                {/* ───────────── One workshop, two ways to sell ───────────── */}
                <OneWorkshop />

                {/* ───────────────────────── Contact ───────────────────────── */}
                <ContactSection />

                {/* ───────────────────── Gallery ───────────────────── */}

                {/* <div>logos</div> */}

                <ArtisanCta />

                <SiteFooter />
            </div>
        </>
    );
}
