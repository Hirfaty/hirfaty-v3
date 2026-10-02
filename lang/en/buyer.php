<?php

// Flat keys — dots are part of the key, NOT nested arrays. Keep in sync with
// lang/fr/buyer.php and lang/ar/buyer.php; a key missing here renders as the
// raw key string on the page rather than falling back.

return [
    // ── Product listing ───────────────────────────────────────────
    'products.hero_eyebrow' => 'Made slowly · Kept for generations',
    'products.hero_title' => 'Discover Moroccan Craftsmanship',
    'products.hero_subtitle' => 'Handmade pieces created by Moroccan artisans, carrying the character of their craft into your home.',

    'products.collection_eyebrow' => 'The full collection',
    'products.collection_title' => 'Objects with a story',
    'products.category_all' => 'All',
    'products.categories' => 'Categories',
    'products.count_pieces' => 'handcrafted pieces',
    'products.made_in_morocco' => 'Made in Morocco',

    'products.search_placeholder' => 'Search handmade products…',
    'products.search_label' => 'Search products',

    'products.reset_all' => 'Reset all',

    'products.view_product' => 'View product',
    'products.wishlist_add' => 'Add to your favourites',
    'products.wishlist_remove' => 'Remove from your favourites',

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

    'products.closing_title' => 'Craft carries memory.',
    'products.closing_subtitle' => 'Thoughtfully sourced from independent workshops across Morocco.',

    // ── Professional / trade pricing ──────────────────────────────
    'products.pro_short' => 'Trade*',
    'products.pro_from_qty' => 'from',
    'products.pro_price_hint' => 'Your trade rate — this is what the checkout charges.',

    // ── New arrivals ──────────────────────────────────────────────
    'new.title' => 'New in',
    'new.eyebrow' => 'Just listed',
    'new.heading' => 'Fresh from the workshops',
    'new.lead' => 'The most recent pieces our artisans have put up, newest first.',
    'new.browse_all' => 'Browse the full collection',
    'new.empty' => 'Nothing has been listed yet — come back very soon.',

    // ── Order history ─────────────────────────────────────────────
    'orders.status_pending' => 'Pending',
    'orders.status_confirmed' => 'Confirmed',
    'orders.status_shipped' => 'Shipped',
    'orders.status_delivered' => 'Delivered',
    'orders.status_cancelled' => 'Cancelled',

    // A card is one checkout, which may span workshops — see
    // BuyerAccountService::purchases().
    'orders.shops_count' => ':count workshops',
    'orders.items_total' => 'Pieces',
    'orders.delivery' => 'Delivery',
    'orders.total' => 'Total',

    // Order card — pages/buyer/orders/index.tsx.
    'orders.number' => 'Order #: :reference',
    'orders.products_one' => '1 product',
    'orders.products_many' => ':count products',
    'orders.by' => 'By :name',
    'orders.status' => 'Status',
    'orders.delivery_date' => 'Date of delivery',
    'orders.delivery_pending' => 'To be confirmed',
    'orders.delivered_to' => 'Delivered to',
    'orders.quantity' => 'Quantity',
    'orders.variant' => 'Option',
    'orders.workshop' => 'Workshop',
    'orders.download_invoice' => 'Download invoice',
    'orders.more_actions' => 'More actions',
    'orders.copy_reference' => 'Copy reference',
    'orders.reference_copied' => 'Reference copied',
    'orders.contact' => 'Contact customer service',
    'orders.track' => 'Order history',
    'orders.rate' => 'Rate my items',
    'orders.my_reviews' => 'See my reviews',
    'orders.back' => 'Back to my orders',

    'tracking.title' => 'Order tracking',
    'tracking.intro' => 'Every step of your order, from confirmation to delivery.',
    'tracking.step_placed' => 'Placed',
    'tracking.step_confirmed' => 'Confirmed',
    'tracking.step_shipped' => 'Shipped',
    'tracking.step_delivered' => 'Delivered',
    'tracking.history' => 'History',
    'tracking.event_pending' => 'Order received',
    'tracking.event_confirmed' => 'Order confirmed with the artisans',
    'tracking.event_shipped' => 'Parcel handed to the carrier',
    'tracking.event_delivered' => 'Parcel delivered',
    'tracking.event_cancelled' => 'Order cancelled',
    'tracking.event_update' => 'Shipping update',
    'tracking.carrier' => 'Carrier',
    'tracking.tracking_number' => 'Tracking number',
    'tracking.only' => 'For: :names',
    'tracking.cancelled_notice' => 'This order has been cancelled.',

    'rate.title' => 'Rate my items',
    'rate.intro' => 'A rating and a review for each piece you received — it helps the artisans and other buyers.',
    'rate.comment_placeholder' => 'What did you think of this piece? Quality, finish, how it compares to the photos…',
    'rate.not_delivered' => 'You can rate it once delivered',
    'rate.submit' => 'Send my reviews',
    'rate.all_done' => 'Thank you! All your delivered pieces have been rated.',

    // Empty state — components/buyer/orders/EmptyOrders.tsx.
    'orders.empty_eyebrow' => 'Welcome',
    'orders.empty_title' => 'Ready to shop?',
    'orders.empty_cta' => 'Shop now',

    // ── Ratings ───────────────────────────────────────────────────
    // Only offered on a delivered order, and only once per piece — see
    // ReviewService. The workshop itself is no longer rated.
    'reviews.heading' => 'How did it go?',
    'reviews.all_rated_heading' => 'Your rating',
    'reviews.rate_product' => 'Rate this piece',
    'reviews.rated' => 'Rated',
    'reviews.star_label' => ':count out of 5',
    'reviews.comment_placeholder' => 'Anything to add? (optional)',
    'reviews.final_notice' => 'A rating cannot be changed once sent.',
    'reviews.submit' => 'Send',
    'reviews.thanks' => 'Thank you.',

    // The dialog a buyer meets on arrival when a delivery is still unrated.
    'reviews.prompt_title' => 'Your order has arrived',
    'reviews.prompt_body' => 'Tell us what you think of the pieces you received. It takes a moment, and it helps the artisan.',
    'reviews.prompt_body_plural' => 'You have :count deliveries waiting to be rated. It takes a moment, and it helps the artisans.',
    'reviews.prompt_cta' => 'Rate my order',
    'reviews.prompt_later' => 'Later',

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
    'detail.delivery' => 'Delivery in 3 to 7 days on average in Morocco, 7 to 14 days abroad.',
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
    'detail.reviews_heading' => 'Buyer reviews',
    'detail.reviews_count_one' => ':n review',
    'detail.reviews_count' => ':n reviews',
    'detail.reviews_out_of' => 'out of 5',
    'detail.reviews_stars_label' => ':n out of 5 stars',
    'detail.reviews_star_row' => ':n stars',
    'detail.reviews_empty' => 'No reviews for this piece yet.',
    'detail.reviews_empty_hint' => 'Reviews are left by buyers once their order has arrived.',
    'detail.reviews_verified' => 'Verified purchase',
    'detail.reviews_anonymous' => 'Hirfaty buyer',
    'detail.reviews_more' => 'Show more reviews',
    'detail.handmade_note' => 'Every piece is handmade, so slight variations in colour and pattern are possible — they are what make yours one of a kind.',
    'detail.specs_heading' => 'Weight and dimensions (packed)',
    'detail.specs_weight' => 'Weight',
    'detail.specs_dimensions' => 'Dimensions (L × W × H)',
    'detail.specs_length' => 'Length',
    'detail.specs_width' => 'Width',
    'detail.specs_height' => 'Height',

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
    'onboarding.title' => 'Welcome',
    'onboarding.skip' => 'Skip',
    'onboarding.back' => 'Back',
    'onboarding.next' => 'Continue',
    'onboarding.finish' => 'Explore the market',

    'onboarding.welcome.title' => 'Welcome, :name',
    'onboarding.welcome.title_generic' => 'Welcome',
    'onboarding.welcome.body' => 'Hirfaty brings Morocco\'s artisans together in one place. Two minutes to get acquainted, then the market is yours.',
    'onboarding.welcome.point_direct' => 'You buy straight from the artisan — no middleman.',
    'onboarding.welcome.point_authentic' => 'Every piece is handmade, and every shop is vetted by our team.',
    'onboarding.welcome.point_delivery' => 'Delivery anywhere in Morocco, tracked from your orders page.',

    'onboarding.interests.title' => 'What draws you in?',
    'onboarding.interests.body' => 'Pick the crafts you care about. We\'ll use them to put what suits you up front.',
    'onboarding.interests.optional' => 'Optional — you can change your mind any time.',

    /*
     * `.placeholder` and `.hint` are the only two of the old trade-request
     * step still in use — the tour no longer asks this, but
     * pages/buyer/professional-account.tsx reuses them for the same question
     * asked later, when a buyer actually requests it. Do not remove.
     */
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

    // ── Account area: four pages sharing the tab strip at the top ────────
    'account.title' => 'My account',
    'account.subtitle' => 'Your details, your orders and the pieces you saved.',
    'account.preferences_title' => 'Language and currency',
    'account.preferences_subtitle' => 'How the site reads to you. Detected from your region unless you pick.',
    'account.currency_auto' => 'Prices are shown in dirhams (MAD), the currency orders are charged in.',

    'account.nav_overview' => 'My account',
    'account.nav_orders' => 'Orders',
    'account.nav_wishlist' => 'Wishlist',
    'account.nav_payment' => 'Payment',
    /*
     * Still used by the standalone /professional-account page's own heading.
     * The account tab strip no longer lists it — see the note in
     * layouts/buyer/account-layout.tsx.
     */
    'account.nav_professional' => 'Professional account',
    'account.logout' => 'Sign out',

    // Which kind of account this is — replaces the avatar and name that used
    // to sit at the top of the rail.
    'account.badge_personal' => 'Personal account',
    'account.badge_professional' => 'Trade account',

    // One card per purchase since the history started grouping them — the
    // split across workshops shows up inside the card, not as extra cards.
    'account.orders_intro' => 'Everything you have ordered. A purchase reaching several workshops stays one order here, with each workshop followed separately.',
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
    'account.field_full_name_empty' => 'Add your name',
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

    // Empty state — shares components/buyer/EmptyShowcase.tsx with orders.
    'wishlist.empty_eyebrow' => 'Nothing saved yet',
    'wishlist.empty_title' => 'Found something you like?',
    'wishlist.empty_cta' => 'Browse the market',

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
    'cart.total' => 'Total',
    'cart.checkout' => 'Checkout',
    'cart.secure_payment' => 'Secure payment',
    'cart.split_note' => 'Your cart will be split into one order per workshop.',

    'cart.you_might_also_like' => 'You might also like',

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

    'checkout.summary' => 'Summary',
    'checkout.products' => 'Items (:count)',
    'checkout.delivery_fee' => 'Delivery fee',
    'checkout.delivery_service_heading' => 'Delivery method',
    'checkout.total' => 'Total',
    'checkout.cod_note' => 'Cash on delivery. You pay the courier directly when your parcels arrive.',
    'checkout.confirm' => 'Place order',
    'checkout.processing' => 'Processing…',

    'checkout.err_address' => 'Please provide a delivery address.',
    'checkout.err_city' => 'Please choose a city.',
    'checkout.err_phone' => 'A phone number is required for delivery.',
    'checkout.err_empty' => 'Your cart is empty.',
    'checkout.customer_heading' => 'Your details',
    'checkout.first_name' => 'First name',
    'checkout.first_name_placeholder' => 'Sarah',
    'checkout.last_name' => 'Last name',
    'checkout.last_name_placeholder' => 'Bennani',
    'checkout.email' => 'Email',
    'checkout.shipping_heading' => 'Delivery address',
    'checkout.postal_code' => 'Postal code',
    'checkout.country' => 'Country',
    'checkout.country_ma' => 'Morocco',
    'checkout.payment_heading' => 'Payment method',
    'checkout.payment_cod' => 'Cash on delivery',
    'checkout.coupon' => 'Discount code',
    'checkout.coupon_placeholder' => 'WELCOME10',
    'checkout.coupon_apply' => 'Apply',
    'checkout.err_first_name' => 'Please enter your first name.',
    'checkout.err_last_name' => 'Please enter your last name.',
    'checkout.err_postal_code' => 'Please enter a postal code.',
    'checkout.err_country' => 'We only deliver within Morocco for now.',
    'checkout.err_payment_method' => 'Choose a payment method.',
    'checkout.err_coupon_unknown' => 'That discount code isn’t valid.',
    'order_placed.eyebrow' => 'Congratulations',
    'order_placed.title' => 'Your order is placed!',
    'order_placed.body' => 'Thank you for your trust. Our artisans are getting to work, and we will keep you posted by email at every step.',
    'order_placed.reference' => 'Reference',
    'order_placed.email' => 'A summary has been sent to :email.',
    'order_placed.view' => 'View my orders',
    'order_placed.continue' => 'Continue shopping',
    'order_placed.summary' => 'Summary',
    'order_placed.next_title' => 'What happens next?',
    'order_placed.step_confirm' => 'Our team confirms your order with the artisans — you get an email as soon as it is done.',
    'order_placed.step_ship' => 'Your pieces are prepared and shipped; an email lets you know when the parcel leaves.',
    'order_placed.step_deliver' => 'You pay on delivery, and can then rate each piece from your orders.',

    // PDF invoice — resources/views/invoices/purchase.blade.php.
    'invoice.title' => 'Invoice',
    'invoice.number' => 'No.',
    'invoice.date' => 'Date:',
    'invoice.billed_to' => 'Billed to',
    'invoice.delivered_to' => 'Delivered to',
    'invoice.carrier' => 'Carrier:',
    'invoice.item' => 'Item',
    'invoice.quantity' => 'Qty',
    'invoice.unit_price' => 'Unit price',
    'invoice.subtotal' => 'Subtotal',
    'invoice.workshop' => 'Workshop:',
    'invoice.items_total' => 'Items total',
    'invoice.delivery' => 'Delivery',
    'invoice.total' => 'Total (incl. tax)',
    'invoice.payment' => 'Payment method: cash on delivery.',
    'invoice.footer' => 'Thank you for supporting Moroccan craftsmanship — hirfaty.com',
    'invoice.filename' => 'hirfaty-invoice-:reference',

    // ── Shop page ────────────────────────────────────────────────────────
    'store.back_to_market' => 'The market',
    'store.type_artist' => 'Artisan',
    'store.type_company' => 'Company',
    'store.type_cooperative' => 'Cooperative',
    'store.since' => 'On Hirfaty since :date',
    // The trust mark beside a shop's name, on the product page and the shop's
    // own page. Reflects the admin approval the shop passed before it could sell.
    'store.verified' => 'Verified by Hirfaty',
    'store.count_one' => ':count piece',
    'store.count_many' => ':count pieces',
    'store.creations' => 'From this workshop',
    'store.empty_title' => 'Nothing listed yet',
    'store.empty_body' => 'This workshop has no published piece yet. Do come back.',
    'store.empty_cta' => 'Explore the market',
    'store.load_more' => 'Show more pieces',
    'store.loading' => 'Loading…',
];
