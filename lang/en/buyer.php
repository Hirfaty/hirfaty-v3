<?php

// Flat keys — dots are part of the key, NOT nested arrays. Keep in sync with
// lang/fr/buyer.php and lang/ar/buyer.php; a key missing here renders as the
// raw key string on the page rather than falling back.

return [
    // ── Product listing ───────────────────────────────────────────
    'products.hero_title_l1' => 'Authentic.',
    'products.hero_title_l2' => 'Handmade.',
    'products.hero_title_l3' => 'Made with heart.',
    'products.hero_subtitle' => 'Discover unique pieces, crafted by talented hands.',
    'products.feature_handmade' => 'Handmade',
    'products.feature_handmade_sub' => 'with care',
    'products.feature_natural' => 'Natural',
    'products.feature_natural_sub' => 'materials',
    'products.feature_unique' => 'Unique',
    'products.feature_unique_sub' => '& authentic',
    'products.feature_artisans' => 'Supporting',
    'products.feature_artisans_sub' => 'artisans',
    'products.hero_slide_pottery' => 'Moroccan potter shaping a piece in their workshop',
    'products.hero_slide_textile' => 'Wool being woven on a traditional loom',
    'products.hero_slide_zellige' => 'Hand-cut zellige tilework, geometric pattern',
    'products.hero_slide_spice' => 'Moroccan spices displayed at the souk',

    'products.search_placeholder' => 'Search a piece, a workshop…',
    'products.search_label' => 'Search products',
    'products.search_clear' => 'Clear search',

    'products.refine' => 'Refine',
    'products.refine_open' => 'Open',
    'products.sort' => 'Sort',
    'products.sort_score' => 'Relevance',
    'products.sort_latest' => 'New in',
    'products.sort_price_asc' => 'Price: low to high',
    'products.sort_price_desc' => 'Price: high to low',
    'products.sort_rating' => 'Top rated',

    'products.price' => 'Price',
    'products.price_max_label' => 'Maximum price',
    'products.in_stock_only' => 'In stock only',
    'products.reset_all' => 'Reset all',

    'products.badge_sale' => 'Sale',
    'products.badge_sold_out' => 'Sold out',
    'products.no_image' => 'No image',

    'products.empty_with_search' => 'No piece matches your search.',
    'products.empty' => 'No piece matches your filters.',

    'products.load_more' => 'Show more pieces',
    'products.loading' => 'Loading…',
    'products.count_of' => 'of',

    // ── Professional / trade pricing ──────────────────────────────
    'products.pro_short' => 'Trade*',
    'products.pro_from_qty' => 'from',
    'products.pro_price_hint' => 'Indicative trade rate — billing remains at the public price for now.',
    'products.pro_notice_title' => 'Trade account.',
    'products.pro_notice_body' => 'Your trade rates appear below the public price. They are indicative: billing remains at the public price for now.',
    'products.pro_cta_body' => 'Buying for a riad, a hotel or a shop? Trade rates are available across much of the catalogue.',
    'products.pro_cta_button' => 'Open a trade account',

    // ── Product detail ────────────────────────────────────────────
    'detail.back_to_market' => 'The market',
    'detail.add_to_cart' => 'Add to basket',
    'detail.go_to_cart' => 'View basket',
    'detail.sold_out' => 'Sold out',
    'detail.in_stock' => 'In stock',
    'detail.only_left' => 'Only :count left',
    'detail.quantity' => 'Quantity',
    'detail.decrease' => 'Decrease',
    'detail.increase' => 'Increase',
    'detail.wishlist_add' => 'Add to wishlist',
    'detail.wishlist_remove' => 'Remove from wishlist',
    'detail.description' => 'Description',
    'detail.no_description' => 'No description provided.',
    'detail.delivery' => 'Amana delivery in 3 to 7 days across Morocco.',
    'detail.authentic' => 'Authentic piece, guaranteed by the workshop.',
    'detail.view_media' => 'View media :n',

    // Seller card
    'detail.sold_by' => 'Sold by',
    'detail.visit_store' => 'Visit the shop',
    'detail.more_from_store' => 'More from this workshop',

    // Pricing
    'detail.public_price' => 'Public price',
    'detail.your_trade_price' => 'Your trade price',
    'detail.trade_saving' => 'a :percent% saving',
    'detail.trade_min_qty' => 'Minimum order: :count pieces',
    'detail.trade_billed_note' => 'This rate is applied automatically at checkout.',
    'detail.trade_cta_title' => 'Buying wholesale?',
    'detail.trade_cta_body' => 'Verified trade accounts get reserved rates across much of the catalogue.',
    'detail.trade_cta_button' => 'Open a trade account',
    'detail.trade_pending_title' => 'Trade account awaiting verification.',
    'detail.trade_pending_body' => 'Your trade rates will apply as soon as an administrator approves your account.',

    // Variants
    'detail.select_variant_prompt' => 'Choose an option',

    // Page furniture
    'detail.home' => 'Home',
    'detail.eyebrow_handmade' => 'Handmade',
    'detail.related' => 'You may also like',
    'detail.handmade_note' => 'Every piece is handmade, so slight variations in colour and pattern are possible — they are what make yours one of a kind.',

    // Reassurance row
    'detail.trust_materials_title' => 'Natural materials',
    'detail.trust_materials_body' => 'High quality clay and natural glazes.',
    'detail.trust_handmade_title' => 'Handmade',
    'detail.trust_handmade_body' => 'Each piece is shaped by hand.',
    'detail.trust_durable_title' => 'Durable',
    'detail.trust_durable_body' => 'High fired ceramic, made to last.',
    'detail.trust_packaging_title' => 'Secure packaging',
    'detail.trust_packaging_body' => 'Carefully packed for a safe delivery.',
];
