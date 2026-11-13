<?php

// Flat keys — dots are part of the key, NOT nested arrays. Keep in sync with
// lang/fr/pro.php and lang/ar/pro.php.
//
// Personal or pro prices (PriceModeService). Written against what the code
// does: any visitor picks their price list (first-visit popup, menu switch),
// checkout bills that list, and in pro mode CartService / CheckoutService
// enforce each piece's minimum (min_order_qty_professional). No request, no
// approval.

return [
    // ── Public "pro prices" page (/professional-account) ─────────────────
    'meta.title' => 'Pro prices',

    'hero.eyebrow' => 'For businesses',
    'hero.title1' => 'Buying for a riad,',
    'hero.title2' => 'a hotel, a shop?',
    'hero.lead' => 'Switch to “Business” and buy at wholesale prices, straight from the artisans. No request, no approval: the only condition is a minimum quantity per piece.',
    'hero.cta' => 'See pro prices',
    'hero.cta_active' => 'Pro prices are on',
    'hero.browse' => 'Browse the catalogue',

    // ── What you get ─────────────────────────────────────────────────────
    'perks.eyebrow' => 'What it gets you',
    'perks.heading' => 'Wholesale, without the middleman',

    'perks.pricing.title' => 'Wholesale rates',
    'perks.pricing.body' => 'In “Business” mode, every piece the workshop offers a wholesale rate for shows at that price — and it is the figure the basket totals and the checkout charges.',

    'perks.catalogue.title' => 'Pieces kept for the trade',
    'perks.catalogue.body' => 'Some workshops list pieces for professionals only. They appear in the catalogue as soon as you switch to “Business”.',

    'perks.direct.title' => 'Straight from the workshop',
    'perks.direct.body' => 'You order from the artisan, not a reseller. The same makers, the same pieces — only the price list differs.',

    // ── The visual demo ──────────────────────────────────────────────────
    'demo.eyebrow' => 'See the difference',
    'demo.heading' => 'The same piece, two price lists',
    'demo.lead' => 'Nothing about the catalogue changes. The pieces, the workshops and the photographs are identical — only the price does, from a minimum quantity.',
    'demo.public_label' => 'In “Personal” mode',
    'demo.trade_label' => 'In “Business” mode',
    'demo.product_name' => 'Glazed tagine, Safi',
    'demo.workshop' => 'Atelier Safi · Safi',
    'demo.badge' => 'Pro',
    'demo.saving' => 'You save :percent%',
    'demo.min_note' => 'From :count pieces',
    'demo.disclaimer' => 'Illustration — figures shown are an example, not a live offer.',

    // ── How it works ─────────────────────────────────────────────────────
    'steps.eyebrow' => 'How it works',
    'steps.heading' => 'Three steps, no request',

    'steps.1.title' => 'Switch to “Business”',
    'steps.1.body' => 'From the menu at the top of every page, or on your very first visit. You can go back to personal prices at any time.',

    'steps.2.title' => 'Pick your quantities',
    'steps.2.body' => 'Each piece shows its minimum quantity at the pro price on its page. The basket keeps to it for you.',

    'steps.3.title' => 'Order at the pro price',
    'steps.3.body' => 'Checkout charges each piece’s pro price. The same delivery and the same workshops as everyone else.',

    // ── Good to know ─────────────────────────────────────────────────────
    'notes.eyebrow' => 'Good to know',
    'notes.heading' => 'The small print, plainly',

    'notes.open.title' => 'No request, no waiting',
    'notes.open.body' => 'There is no trade account to apply for and no approval to wait on: the pro price applies as soon as you switch to “Business”.',

    'notes.minimum.title' => 'A minimum quantity per piece',
    'notes.minimum.body' => 'A wholesale rate assumes an order in quantity, so each piece can have a minimum at the pro price, set by our team. It is shown on the product page, and the basket keeps to it.',

    'notes.switch.title' => 'Fewer pieces? Personal price',
    'notes.switch.body' => 'Need just one piece? Switch back to “Personal”: the public price applies, from a single piece.',

    // ── Closing ──────────────────────────────────────────────────────────
    'cta.heading' => 'Ready to order?',
    'cta.body' => 'Switch to “Business” and see the whole catalogue at wholesale prices.',
    'cta.active_heading' => 'You are in “Business” mode',
    'cta.active_body' => 'Wholesale prices show across the whole catalogue. All that is left is picking your pieces.',

    // ── The personal / pro switch ────────────────────────────────────────
    'mode.label' => 'Prices shown',
    'mode.aria' => 'Show personal or pro prices',
    'mode.individual' => 'Personal',
    'mode.professional' => 'Business',
    'mode.individual_hint' => 'Public prices, from one piece',
    'mode.professional_hint' => 'Wholesale prices, minimum quantity',

    // The first-visit question.
    'chooser.title' => 'How would you like to shop?',
    'chooser.lead' => 'Choose your price list. You can change it anytime.',
    'chooser.professional.title' => 'I’m shopping for a business',
    'chooser.professional.hint' => 'Professional prices',
    'chooser.individual.title' => 'I’m shopping for myself',
    'chooser.individual.hint' => 'Personal prices',
    'chooser.footnote' => 'No pressure: switch whenever you like from the menu.',
    'chooser.close' => 'Close',

    // Banner above the grids in pro mode.
    'notice.title' => 'Pro prices shown.',
    'notice.body' => 'Each piece is ordered from its minimum quantity at the pro price, shown on its page.',
    'notice.show_retail' => 'See personal prices',

    // Prices on the cards and the product page.
    'price.pro' => 'Pro price',
    'price.tag' => 'Pro',
    'price.public' => 'Public price',
    'price.no_pro_rate' => 'No trade rate on this piece: this is the public price.',
    'price.see_pro' => 'See pro prices',
    'price.min_qty' => 'Minimum :count pieces at the pro price',
    'price.fewer' => 'Fewer than :count pieces?',
    'price.buy_retail' => 'Buy at the personal price',

    // ── Fiche produit en mode particulier : le même article au prix pro ──
    'offer.eyebrow' => 'Buying for your business?',
    'offer.current' => 'Your price',
    'offer.personal' => 'Personal',
    'offer.pro' => 'Professional',
    'offer.from_one' => 'From 1 piece',
    'offer.from_qty' => 'From :count pieces',
    'offer.per_piece' => 'per piece',
    'offer.saving' => 'You save :amount on an order of :count pieces.',
    'offer.cta' => 'Switch to Business',
];
