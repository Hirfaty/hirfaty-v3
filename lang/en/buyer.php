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
    // Demo-only: an unpriced product is temporarily shown in the
    // storefront rather than hidden — see Product::scopeStorefront().
    'products.price_coming_soon' => 'Price coming soon',

    'products.empty_with_search' => 'No piece matches your search.',
    'products.empty' => 'No piece matches your filters.',

    'products.load_more' => 'Show more pieces',
    'products.loading' => 'Loading…',
    'products.count_of' => 'of',

    // ── Professional / trade pricing ──────────────────────────────
    'products.pro_short' => 'Trade*',
    'products.pro_from_qty' => 'from',
    'products.pro_price_hint' => 'Your trade rate — this is what the checkout charges.',
    'products.pro_notice_title' => 'Trade account.',
    'products.pro_notice_body' => 'Your trade rates appear below the public price, and are what the checkout charges.',
    'products.pro_cta_body' => 'Buying for a riad, a hotel or a shop? Trade rates are available across much of the catalogue.',
    'products.pro_cta_button' => 'Open a trade account',

    // ── Product detail ────────────────────────────────────────────
    'detail.back_to_market' => 'The market',
    'detail.add_to_cart' => 'Add to basket',
    'detail.go_to_cart' => 'View basket',
    'detail.sold_out' => 'Out of stock',
    'detail.price_coming_soon' => 'Price coming soon',
    'detail.in_stock' => 'In stock',
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

    // Sign-in gate
    'detail.login_to_buy' => 'Sign in to buy',
    'detail.login_to_buy_hint' => 'Your basket and your prices follow your account.',
    'detail.login_to_buy_register' => 'Create an account',
    'detail.below_min_qty' => 'Not enough stock for your minimum order',
    'detail.below_min_stock' => 'Only :count left — your minimum order is :min',

    // Add to cart feedback
    'detail.adding' => 'Added to cart',
    'detail.added_to_cart' => 'Added to your cart',
    'detail.add_failed' => 'Could not add to cart.',

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

    // ── Onboarding (a buyer's first visit) ───────────────────────────────
    'onboarding.title' => 'Welcome to Hirfaty',
    'onboarding.skip' => 'Skip',
    'onboarding.back' => 'Back',
    'onboarding.next' => 'Continue',
    'onboarding.finish' => 'Explore the market',

    'onboarding.welcome.quote' => 'Behind every piece there are two hands and a craft passed down.',
    'onboarding.welcome.title' => 'Welcome, :name 👋',
    'onboarding.welcome.body' => 'Hirfaty brings Morocco\'s artisans together in one place. Two minutes to get acquainted, then the market is yours.',
    'onboarding.welcome.point_direct' => 'You buy straight from the artisan — no middleman.',
    'onboarding.welcome.point_authentic' => 'Every piece is handmade, and every shop is vetted by our team.',
    'onboarding.welcome.point_delivery' => 'Delivery anywhere in Morocco, tracked from your orders page.',

    'onboarding.interests.quote' => 'Safi pottery, Taznakht rugs, Fès leather — Morocco has a thousand crafts.',
    'onboarding.interests.title' => 'What draws you in?',
    'onboarding.interests.body' => 'Pick the crafts you care about. We\'ll use them to put what suits you up front.',
    'onboarding.interests.optional' => 'Optional — you can change your mind any time.',

    'onboarding.professional.quote' => 'Riads, hotels, boutiques: they furnish their spaces with our artisans.',
    'onboarding.professional.title' => 'Buying for a business?',
    'onboarding.professional.body' => 'Riad, hotel, restaurant, shop… If you order in quantity, your account can become professional.',
    'onboarding.professional.perk' => 'Professional accounts get wholesale pricing. Our team reviews every request before activating it.',
    'onboarding.professional.cta' => 'Request a professional account',
    'onboarding.professional.label' => 'Tell us about your business',
    'onboarding.professional.placeholder' => 'e.g. I run an 8-room riad in Marrakech and I\'m looking for household linen and handmade tableware in quantity.',
    'onboarding.professional.hint' => '20 characters minimum. You can carry on ordering as normal while we review your request.',

    // ── Professional account (standalone page) ───────────────────────────
    'professional.title' => 'Professional account',
    'professional.intro' => 'Riad, hotel, restaurant, shop: if you buy in quantity, request access to wholesale pricing.',
    'professional.label' => 'Tell us about your business',
    'professional.submit' => 'Send my request',
    'professional.pending_title' => 'Request under review',
    'professional.pending_body' => 'Our team is looking at your request. You\'ll get an email as soon as it\'s decided — you can carry on ordering as normal in the meantime.',
    'professional.approved_title' => 'Professional account active',
    'professional.approved_body' => 'Wholesale pricing applies automatically on the products that have it.',
    'professional.declined_title' => 'Request not approved',
    'professional.declined_reason' => 'Reason',
    'professional.declined_retry' => 'You\'re welcome to submit a new request.',
    'professional.submitted_on' => 'Sent on :date',

    // ── Account area: four pages sharing the left sidebar ────────────────
    'account.title' => 'My account',
    'account.subtitle' => 'Your details, your orders and the pieces you saved.',

    'account.nav_overview' => 'My account',
    'account.nav_orders' => 'Orders',
    'account.nav_wishlist' => 'Wishlist',
    'account.nav_payment' => 'Payment',
    'account.nav_professional' => 'Professional account',
    'account.logout' => 'Sign out',

    'account.orders_intro' => 'A basket is split into one order per workshop, so a single purchase can appear here more than once.',
    'account.wishlist_intro' => 'The pieces you saved, kept until you remove them.',
    'account.payment_intro' => 'The cards and methods used to pay for your orders.',
    'account.professional_intro' => 'Request trade access, or check the status of a request already sent.',

    'account.placeholder' => 'Nothing to show here yet.',

    // ── /account: identity, password, default address ────────────────────
    'account.section_identity' => 'Personal details',
    'account.section_identity_desc' => 'The name your orders and delivery notes are made out to.',
    'account.section_password' => 'Password',
    'account.section_password_desc' => 'Choose a new password. You will stay signed in on this device.',
    'account.section_address' => 'Default delivery address',
    'account.section_address_desc' => 'Where your orders are sent. Checkout fills this in for you.',

    'account.field_full_name' => 'Full name',
    'account.field_first_name' => 'First name',
    'account.field_last_name' => 'Last name',
    'account.field_email' => 'Email address',
    'account.field_phone' => 'Phone',
    'account.locked_note' => 'Your email address and phone number can\'t be changed here for now — get in touch and we\'ll update them for you.',

    'account.field_password' => 'Password',
    'account.change_password' => 'Change password',
    'account.field_current_password' => 'Current password',
    'account.field_new_password' => 'New password',
    'account.field_confirm_password' => 'Confirm new password',

    'account.no_address' => 'No address saved yet.',
    'account.add_address' => 'Add an address',
    'account.field_address_label' => 'Address name',
    'account.field_address_label_hint' => 'Optional — for your own reference.',
    'account.field_address_label_ph' => 'Home, Riad, Shop…',
    'account.field_city' => 'City',
    'account.field_city_ph' => 'Choose a city',
    'account.field_address_line' => 'Address',
    'account.field_address_line_ph' => 'No. 12, rue des Potiers, district…',
    'account.field_recipient' => 'Recipient',
    'account.field_recipient_phone' => 'Recipient\'s phone',

    'account.saved' => 'Saved.',
    'account.update_password' => 'Update password',
    'account.password_saved' => 'Password updated.',
    'account.save_address' => 'Save address',

    'account.profile_saved' => 'Your details have been updated.',
    'account.address_saved' => 'Your delivery address has been saved.',
    'account.address_default_saved' => 'Default delivery address updated.',
    'account.address_deleted' => 'Address deleted.',

    'account.err_first_name' => 'Please enter your first name.',
    'account.err_last_name' => 'Please enter your last name.',
    'account.err_address_line' => 'Please enter the delivery address.',
    'account.err_phone' => 'Please enter a phone number for delivery.',
    'account.err_city' => 'Please choose a city.',

    // ── Wishlist ─────────────────────────────────────────────────────────
    'wishlist.title' => 'My wishlist',

    // ── Cart ─────────────────────────────────────────────────────────────
    'cart.title' => 'Your cart',
    'cart.back' => 'Keep browsing',
    'cart.count_one' => ':count item',
    'cart.count_many' => ':count items',

    'cart.empty_title' => 'Your cart is empty',
    'cart.empty_body' => 'Browse the market and let our artisans’ work surprise you.',
    'cart.empty_cta' => 'Explore the market',

    'cart.sold_by' => 'by :store',
    'cart.remove' => 'Remove',
    'cart.decrease' => 'Decrease quantity',
    'cart.increase' => 'Increase quantity',
    'cart.per_unit' => 'each',
    'cart.stock_left' => 'Only :count left in stock',
    'cart.min_qty' => 'Minimum order: :count',
    'cart.unpriced' => 'Price to be confirmed — remove this item to continue.',

    'cart.summary' => 'Summary',
    'cart.subtotal' => 'Subtotal',
    'cart.savings' => 'Savings',
    'cart.delivery' => 'Amana delivery',
    'cart.delivery_one_store' => 'One workshop',
    'cart.delivery_many_stores' => ':count workshops',
    'cart.total' => 'Total',
    'cart.checkout' => 'Checkout',
    'cart.secure_payment' => 'Secure payment',
    'cart.split_note' => 'Your cart will be split into one order per workshop.',

    // ── Checkout ─────────────────────────────────────────────────────────
    'checkout.title' => 'Complete your order',
    'checkout.back' => 'Back to cart',

    'checkout.delivery_heading' => 'Delivery',
    'checkout.saved_addresses' => 'Your saved addresses',
    'checkout.default_badge' => 'Default',
    'checkout.use_new_address' => 'Use another address',
    'checkout.new_address' => 'New address',
    'checkout.city' => 'City',
    'checkout.address_line' => 'Full address',
    'checkout.address_placeholder' => 'Street, neighbourhood, house number…',
    'checkout.phone' => 'Phone',
    'checkout.phone_hint' => 'The Amana courier will call you on this number.',
    'checkout.phone_placeholder' => '06 12 34 56 78',
    'checkout.save_address' => 'Save this address to my address book',
    'checkout.address_label' => 'Address name',
    'checkout.address_label_placeholder' => 'Home, office…',
    'checkout.notes' => 'Notes',
    'checkout.optional' => 'optional',
    'checkout.notes_placeholder' => 'Instructions for the courier…',

    'checkout.shipments_heading' => 'Shipment breakdown',
    'checkout.shipments_note_one' => 'Your cart comes from a single workshop: you will receive one parcel.',
    'checkout.shipments_note_many' => 'Your cart comes from :count workshops: you will receive :count separate parcels.',
    'checkout.shipped_by' => 'Shipped by :store',
    'checkout.quantity' => 'Quantity: :count',
    'checkout.amana_standard' => 'Amana delivery (standard)',

    'checkout.summary' => 'Summary',
    'checkout.products' => 'Items (:count)',
    'checkout.delivery_fee' => 'Delivery (:count parcels)',
    'checkout.total' => 'Total',
    'checkout.cod_note' => 'Cash on delivery. You pay the Amana courier directly when your parcels arrive.',
    'checkout.confirm' => 'Place order',
    'checkout.processing' => 'Processing…',

    'checkout.err_address' => 'Please provide a delivery address.',
    'checkout.err_city' => 'Please choose a city.',
    'checkout.err_phone' => 'A phone number is required for delivery.',
    'checkout.err_empty' => 'Your cart is empty.',
    'checkout.placed' => ':count order(s) placed successfully!',

    // ── Shop page ────────────────────────────────────────────────────────
    'store.back_to_market' => 'The market',
    'store.type_artist' => 'Artisan',
    'store.type_company' => 'Company',
    'store.type_cooperative' => 'Cooperative',
    'store.since' => 'On Hirfaty since :date',
    'store.count_one' => ':count piece',
    'store.count_many' => ':count pieces',
    'store.creations' => 'From this workshop',
    'store.empty_title' => 'Nothing listed yet',
    'store.empty_body' => 'This workshop has no published piece yet. Do come back.',
    'store.empty_cta' => 'Explore the market',
    'store.load_more' => 'Show more pieces',
    'store.loading' => 'Loading…',
];
