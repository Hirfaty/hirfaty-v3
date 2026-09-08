<?php

// Flat keys — dots are part of the key, NOT nested arrays. Keep in sync with
// lang/fr/pro.php and lang/ar/pro.php.
//
// Copy for the public trade-account explainer (/pro). Everything here is
// written against what the code actually does: Product::audienceFor() gates
// the wholesale list on a *verified* professional account, CheckoutService
// bills that list, and CartService enforces min_order_qty_professional. If
// any of those change, this page has to change with them.

return [
    'meta.title' => 'Trade accounts',

    'hero.eyebrow' => 'For businesses',
    'hero.title1' => 'Buying for a riad,',
    'hero.title2' => 'a hotel, a shop?',
    'hero.lead' => 'A trade account gets you reserved wholesale rates across much of the catalogue — bought straight from the artisan who made the piece, the same as everyone else.',
    'hero.cta_guest' => 'Create an account',
    'hero.cta_buyer' => 'Request trade access',
    'hero.cta_pending' => 'Your request is being reviewed',
    'hero.cta_verified' => 'Your trade account is active',
    'hero.browse' => 'Browse the catalogue',

    // ── What you get ─────────────────────────────────────────────────────
    'perks.eyebrow' => 'What it gets you',
    'perks.heading' => 'Wholesale, without the middleman',

    'perks.pricing.title' => 'Your own price list',
    'perks.pricing.body' => 'Verified trade accounts see a reserved rate on every piece the workshop offers one for. It shows on the product page as your price — and it is the figure the basket totals and the checkout charges, not an indication.',

    'perks.catalogue.title' => 'Pieces kept for the trade',
    'perks.catalogue.body' => 'Some workshops list pieces for professionals only. Those appear in your catalogue and nobody else’s.',

    'perks.direct.title' => 'Straight from the workshop',
    'perks.direct.body' => 'You order from the artisan, not a reseller. The same makers, the same pieces — only the price list differs.',

    // ── What you actually see (the visual demo) ──────────────────────────
    'demo.eyebrow' => 'See the difference',
    'demo.heading' => 'The same piece, two price lists',
    'demo.lead' => 'Nothing about the catalogue changes. The pieces, the workshops and the photographs are identical — only the figure on the card is yours.',
    'demo.public_label' => 'What everyone sees',
    'demo.trade_label' => 'What you see',
    'demo.product_name' => 'Glazed tagine, Safi',
    'demo.workshop' => 'Atelier Safi · Safi',
    'demo.badge' => 'Trade',
    'demo.saving' => 'You save :percent%',
    'demo.min_note' => 'From :count pieces',
    'demo.disclaimer' => 'Illustration — figures shown are an example, not a live offer.',

    // ── How it works ─────────────────────────────────────────────────────
    'steps.eyebrow' => 'How it works',
    'steps.heading' => 'Three steps',

    'steps.1.title' => 'Create your account',
    'steps.1.body' => 'An ordinary buyer account, free, in a minute. You can browse and order at public prices straight away.',

    'steps.2.title' => 'Tell us about your business',
    'steps.2.body' => 'A few words on what you do and what you buy in quantity — a riad furnishing rooms, a shop restocking, a restaurant setting tables.',

    'steps.3.title' => 'We review and get back to you',
    'steps.3.body' => 'A person reads every request. You are notified by email either way, and your rates apply the moment the account is approved.',

    // ── Good to know ─────────────────────────────────────────────────────
    'notes.eyebrow' => 'Good to know',
    'notes.heading' => 'The small print, plainly',

    'notes.review.title' => 'Approval is not automatic',
    'notes.review.body' => 'Wholesale rates are granted on the understanding that you buy as a business, so an administrator reviews each request rather than the account granting itself. Until it is approved you pay public prices — nothing about your account breaks in the meantime.',

    'notes.minimum.title' => 'Some pieces have a minimum order',
    'notes.minimum.body' => 'A wholesale rate is offered on the understanding the trade buys in quantity, so a workshop can set a minimum for a piece. Where there is one it is shown on the product page, and the basket holds you to it.',

    'notes.same.title' => 'Everything else is unchanged',
    'notes.same.body' => 'The same delivery, the same workshops, the same order history. A trade account changes which price list you are on — nothing else.',

    // ── Closing ──────────────────────────────────────────────────────────
    'cta.heading' => 'Ready when you are',
    'cta.body' => 'Tell us about your business and we will come back to you quickly.',
];
