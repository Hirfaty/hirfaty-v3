<?php

/*
 * Search-engine and link-preview copy: <meta name="description">, Open Graph
 * and the few labels SeoService puts into structured data.
 *
 * Server-only on purpose — SeoService renders these into the first HTML
 * response, where crawlers and WhatsApp/Facebook previews read them. That is
 * why this file is not in HandleInertiaRequests::getTranslations(): the React
 * pages never need it.
 *
 * Descriptions stay under ~155 characters: Google truncates longer ones, and
 * the tail is where a cut would land. `:placeholders` are filled per page.
 */
return [
    'default' => 'Hirfaty, the marketplace for Moroccan craft: pottery, zellige, leather, textiles and wood, bought directly from the artisans.',

    'home' => 'Buy Moroccan craft directly from the artisans: pottery, zellige, leather, textiles, wood. One-of-a-kind pieces shipped across Morocco and abroad.',
    'catalogue' => 'Every handmade piece from Hirfaty’s Moroccan artisans: home decor, tableware, jewellery, leather and textiles.',
    'catalogue_category' => 'Handmade :category by Moroccan artisans, bought directly on Hirfaty. One-of-a-kind pieces shipped across Morocco and abroad.',
    'new' => 'The latest pieces from Hirfaty’s artisans: new handmade pottery, leather, textiles, woodwork and Moroccan decor.',
    'blog' => 'The Hirfaty Journal: artisan portraits, Moroccan craft traditions and the stories behind handmade pieces.',
    'about' => 'The story of Hirfaty: a marketplace connecting Moroccan artisans directly with their buyers, with no middleman.',
    'how' => 'How Hirfaty works: explore the workshops, order directly from the artisans, and have your piece delivered to your door.',
    'contact' => 'A question about an order, or want to open your workshop on Hirfaty? Write to us — we reply within two working days.',
    'affiliate' => 'The Hirfaty affiliate programme: share Moroccan craft with your audience and earn a commission on every sale.',
    'rewards' => 'Hirfaty rewards: earn points on every order of Moroccan craft and spend them on your next one.',
    'privacy' => 'Hirfaty’s privacy policy: what we collect, why, who can see it and how to exercise your rights.',
    'shipping' => 'Hirfaty’s shipping policy: delivery times, rates and destinations across Morocco and internationally.',
    'terms' => 'Hirfaty’s terms of use for buyers and artisans on the marketplace.',
    'business' => 'Hirfaty for business: buy Moroccan craft in volume, at prices reserved for trade accounts.',
    'sell' => 'Are you a Moroccan artisan? Open your workshop on Hirfaty and sell your work across Morocco and abroad.',
    'professional' => 'Hirfaty trade accounts: dedicated pricing and bulk orders for hotels, interior designers and shops.',

    'product' => ':product, handmade by :store. Buy it directly from the artisan on Hirfaty, shipped across Morocco and abroad.',
    'store' => ':store, a Moroccan craft workshop. Discover their handmade pieces and buy directly from the artisan on Hirfaty.',
    'store_city' => ':store, a craft workshop in :city. Discover their handmade pieces and buy directly from the artisan on Hirfaty.',

    'breadcrumb.home' => 'Home',
];
