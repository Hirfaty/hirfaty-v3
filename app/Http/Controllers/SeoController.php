<?php

namespace App\Http\Controllers;

use App\Services\GoogleMerchantFeedService;
use App\Services\SeoService;
use App\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, served by Laravel rather than as static files
 * so they can differ between production and staging and list live content.
 * (A file at public/robots.txt would shadow the route — nginx serves static
 * files first — which is why there isn't one.)
 *
 * The Google Merchant Center product feed lives here for the same reason.
 */
class SeoController extends Controller
{
    public function robots(Request $request, SeoService $seo): Response
    {
        return response($seo->robotsTxt($request), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(SitemapService $sitemap): Response
    {
        return response($sitemap->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function googleFeed(string $language, GoogleMerchantFeedService $feed): Response
    {
        return response($feed->xml($language), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
