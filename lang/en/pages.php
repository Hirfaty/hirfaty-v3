<?php

/*
 * The two standalone pages the footer links to: "How it works" and "Contact".
 *
 * Contact *details* are not repeated here — the page reads them from
 * `welcome.contact.*`, which the homepage's contact section already uses, so
 * there is one place to change the address and phone number rather than two
 * that can drift apart.
 */
return [
    // ── How it works ─────────────────────────────────────────────────────
    'how.title' => 'How it works',
    'how.eyebrow' => 'Hirfaty in short',
    'how.heading' => 'From the workshop to your door',
    'how.lead' => 'Hirfaty is the marketplace for Moroccan craft: artisans sell what they make themselves, directly, with nobody standing between their workbench and your home.',

    'how.step1.title' => 'Browse the souk',
    'how.step1.body' => 'Every workshop has its own shop. Browse by category or by city, and stop wherever your eye stops — the way you would in a real medina.',
    'how.step2.title' => 'Order directly',
    'how.step2.body' => 'Your order goes to the artisan who made the piece. They prepare it themselves, and most of the price goes to them.',
    'how.step3.title' => 'Amana delivers',
    'how.step3.body' => 'Tracked delivery to your door, anywhere in Morocco. You follow the order from your account, from the first day to the day it arrives.',

    'how.sellers.heading' => 'Are you an artisan?',
    'how.sellers.body' => 'Open your shop on Hirfaty, list your pieces, and sell to private buyers and to the trade alike — from a single dashboard.',
    'how.sellers.cta' => 'Sell on Hirfaty',

    'how.buyers.cta' => 'Browse the products',

    // ── Contact ──────────────────────────────────────────────────────────
    'contact.title' => 'Contact',
    'contact.eyebrow' => 'Write to us',
    'contact.heading' => 'Let’s talk',
    'contact.lead' => 'A question about an order, a shop you would like to open, or simply want to know more? Write to us — we answer within two working days.',

    'contact.hours_label' => 'Hours',
    'contact.hours' => 'Monday to Friday, 9am – 6pm (GMT+1)',

    'contact.seller.heading' => 'Want to sell on Hirfaty?',
    'contact.seller.body' => 'Create your artisan account: putting your shop online takes a few minutes, and our team reviews it before it goes live.',
    'contact.seller.cta' => 'Open my shop',

    // ── Affiliate program ────────────────────────────────────────────────
    // Deliberately quotes no commission rate: terms are agreed per partner
    // for now, and a number printed here would be the one thing nobody
    // remembers to update. The shape mirrors the reference programme — who
    // may join, what you get, how to apply.
    'affiliate.title' => 'Affiliate program',
    'affiliate.eyebrow' => 'Partner with Hirfaty',
    'affiliate.heading' => 'Share Moroccan craft. Earn from every order.',
    'affiliate.lead' => 'If you write, film or post about design, travel or Morocco, our affiliate program turns that audience into income — and sends work to the workshops behind every piece.',

    'affiliate.how.heading' => 'How it works',
    'affiliate.how.step1.title' => 'Apply',
    'affiliate.how.step1.body' => 'Send us your profile and where you publish. We answer within about five working days.',
    'affiliate.how.step2.title' => 'Share your link',
    'affiliate.how.step2.body' => 'You get a personal link and a set of photographs you are free to use. Post wherever your audience is.',
    'affiliate.how.step3.title' => 'Earn on every sale',
    'affiliate.how.step3.body' => 'Any order placed through your link earns you a commission, paid monthly by bank transfer.',

    'affiliate.who.heading' => 'Who we are looking for',
    'affiliate.who.body' => 'Creators with an engaged audience — the size matters less than whether people actually listen to you. Interior designers, travel writers, food and craft accounts, and anyone whose readers care where things come from.',
    'affiliate.who.point1' => 'An active account on any major platform, posted to in the last two weeks.',
    'affiliate.who.point2' => 'Content that sits naturally beside handmade Moroccan work.',
    'affiliate.who.point3' => 'Anywhere in the world — we ship well beyond Morocco.',

    'affiliate.terms.heading' => 'Commission and payment',
    'affiliate.terms.body' => 'Rates are agreed with each partner rather than fixed, so a food creator and an interiors studio are not squeezed into the same deal. We state your rate, your attribution window and your payment schedule in writing before you start.',

    'affiliate.cta.heading' => 'Apply to join',
    'affiliate.cta.body' => 'Write to us with your name, where you publish and a link or two. That is the whole application.',
    'affiliate.cta.button' => 'Email us',

    // ── Rewards ──────────────────────────────────────────────────────────
    // The points programme is not built: nothing here reads a balance, and
    // the closing section says so plainly rather than implying an account
    // already has points sitting in it.
    'rewards.title' => 'Rewards',
    'rewards.eyebrow' => 'Hirfaty rewards',
    'rewards.heading' => 'Every order brings you closer to the next one.',
    'rewards.lead' => 'Collect points whenever you shop, then spend them on what you buy next. Free to join, nothing to remember.',

    'rewards.earn.heading' => 'Earning points',
    'rewards.earn.shop.title' => 'Shop',
    'rewards.earn.shop.body' => '1 point for every 1 MAD spent.',
    'rewards.earn.signup.title' => 'Create an account',
    'rewards.earn.signup.body' => '200 points, once.',
    'rewards.earn.review.title' => 'Leave a review',
    'rewards.earn.review.body' => '100 points per piece you have received.',
    'rewards.earn.birthday.title' => 'Your birthday',
    'rewards.earn.birthday.body' => '250 points, every year.',

    'rewards.spend.heading' => 'Spending them',
    'rewards.spend.body' => 'Points come off your basket at checkout. 100 points is 10 MAD — so an order of 500 MAD earns you 50 MAD towards the next one.',
    'rewards.spend.note' => 'Points never expire while your account is active, and they apply to the whole basket, not to one workshop.',

    'rewards.tiers.heading' => 'Tiers',
    'rewards.tiers.body' => 'The more you shop in a year, the faster you collect.',
    'rewards.tiers.silver' => 'Silver',
    'rewards.tiers.silver_body' => 'From your first order. 1 point per MAD.',
    'rewards.tiers.gold' => 'Gold',
    'rewards.tiers.gold_body' => 'From 3,000 MAD a year. 1.25 points per MAD, plus early access to new pieces.',
    'rewards.tiers.platinum' => 'Platinum',
    'rewards.tiers.platinum_body' => 'From 8,000 MAD a year. 1.5 points per MAD, plus free delivery in Morocco.',

    'rewards.soon.heading' => 'Coming soon',
    'rewards.soon.body' => 'The rewards programme is being built. Create your account now and your orders will count towards it from the day it opens.',
    'rewards.soon.button' => 'Browse the market',

    // ── Privacy policy ───────────────────────────────────────────────────
    'privacy.title' => 'Privacy policy',
    'privacy.eyebrow' => 'Your data',
    'privacy.heading' => 'What we collect, and what we do with it.',
    'privacy.lead' => 'Short version: we collect what an order needs and nothing we cannot justify. We do not sell your data.',
    'privacy.updated' => 'Last updated: October 2026',

    // Law 09-08 — translated from the company's French wording (CNDP receipt D-W-6-2026).
    'privacy.compliance.heading' => 'Compliance & data protection (Law 09-08)',
    'privacy.compliance.publisher' => 'The www.hirfaty.com platform is published by CREATI DIGITAL FINANCE (Trade register no. 13075, registered office: 19 Rue Om Ayad, Quartier Mly Hassan, Safi, Morocco).',
    'privacy.compliance.declaration' => 'In accordance with Law no. 09-08 on the protection of individuals with regard to the processing of personal data, our processing of account registrations has been declared in advance to the CNDP under number D-W-6-2026 (receipt dated 06/01/2026, declaration number: D-6/2026).',
    'privacy.compliance.rights' => 'You have the right to access, correct and object to the data concerning you by contacting us at:',
    'privacy.compliance.logo_alt' => 'CNDP — Morocco’s national commission for the protection of personal data',

    'privacy.collect.heading' => 'What we collect',
    'privacy.collect.body1' => 'When you create an account: your name, email address and phone number. When you order: the delivery address and the phone number the courier should call.',
    'privacy.collect.body2' => 'We also read the country your connection appears to come from, used only to decide which currency to show you. You can change that from the header at any time.',

    'privacy.use.heading' => 'What we use it for',
    'privacy.use.body' => 'Taking and delivering your order, answering you when you write to us, and sending the emails that belong to an order — confirmations, dispatch notices and the like. Marketing email goes out only if you asked for it, and every one carries a way out.',

    'privacy.share.heading' => 'Who sees it',
    'privacy.share.body' => 'The workshop fulfilling your order sees what it needs to prepare it. Our delivery partner sees the address and phone number so they can deliver. Your card details are typed directly into Payzone, our payment provider — Hirfaty never sees them. Nobody else — we do not sell or rent your details.',

    'privacy.rights.heading' => 'Your rights',
    'privacy.rights.body' => 'You can ask for a copy of what we hold on you, ask us to correct it, or ask us to close your account. Write to us and we will answer. Some order records have to be kept for accounting reasons even after an account is closed.',

    'privacy.contact.heading' => 'Getting in touch',
    'privacy.contact.body' => 'Questions about any of this, or a request about your data, go to the same address:',

    // ── Shipping policy ──────────────────────────────────────────────────
    // Figures here are the real ones: DeliveryRateSeeder charges a flat 40
    // MAD in Morocco and CheckoutRequest accepts card payment (Payzone) only.
    'shipping.title' => 'Shipping policy',
    'shipping.eyebrow' => 'Delivery',
    'shipping.heading' => 'How your order reaches you.',
    'shipping.lead' => 'Hirfaty collects your order from the workshops that made it and sends it out as one parcel.',

    'shipping.morocco.heading' => 'Within Morocco',
    'shipping.morocco.body' => 'A flat 40 MAD per order, wherever in the country you are — one delivery charge for the whole basket, even when it comes from several workshops. Delivery usually takes 3 to 7 days.',

    'shipping.abroad.heading' => 'Outside Morocco',
    'shipping.abroad.body' => 'We ship to Europe, North America and the Gulf. The charge depends on the destination and is shown at checkout before you confirm — nothing is added afterwards.',

    'shipping.payment.heading' => 'Paying',
    'shipping.payment.body' => 'Orders are paid online, by bank card (Visa, Mastercard), through Payzone’s secure platform (3D Secure), when you place them. There is nothing to pay the courier. The amount is charged in dirhams: if the site is showing prices in another currency, that is a guide.',

    'shipping.tracking.heading' => 'Following your order',
    'shipping.tracking.body' => 'You get an email when the workshop confirms your order, and another when it goes out for delivery with a tracking number. Everything is on your orders page too.',

    'shipping.handmade.heading' => 'A note on handmade work',
    'shipping.handmade.body' => 'Every piece is made by hand, and some are made once you order them. If a piece needs longer than usual in the workshop, we tell you rather than let the date slip quietly.',

    // ── Terms of Service ────────────────────────────────────────────────
    // Same rule as privacy: describes what the app actually does. Cash on
    // delivery, cancellation limited to still-'pending' orders, product
    // moderation and account suspension are all rules the code already
    // enforces, not promises on top of it.
    'terms.title' => 'Terms of Service',
    'terms.eyebrow' => 'Read before you order',
    'terms.heading' => 'The rules of the game, plainly.',
    'terms.lead' => 'Using Hirfaty, whether to buy or to sell, means agreeing to what follows. It is written to be read, not skipped.',
    'terms.updated' => 'Last updated: September 2026',

    'terms.marketplace.heading' => 'What Hirfaty is',
    'terms.marketplace.body' => 'Hirfaty is a marketplace: every piece you see is made and offered for sale by the workshop named on its page. We run the site, process orders and arrange delivery, but the workshop stays responsible for what it makes.',

    'terms.accounts.heading' => 'Your account',
    'terms.accounts.body' => 'An account can be a shopper, a trade (bulk-buying) account, or an artisan. The details you give must be accurate, and you are responsible for what happens on your account. You can close it at any time from your settings.',

    'terms.orders.heading' => 'Orders and payment',
    'terms.orders.body' => 'Orders are paid online, by bank card (Visa, Mastercard), through Payzone’s secure platform (3D Secure). An order is placed only once the payment is accepted; the amount is charged in Moroccan dirhams (MAD). Hirfaty never receives or stores your card details. If a paid order is cancelled, its amount is refunded to the card you used. If the site shows a price in another currency, that is for reference only — the amount actually charged is the one shown in dirhams at checkout.',

    'terms.cancellation.heading' => 'Cancelling an order',
    'terms.cancellation.body' => 'An order is confirmed as soon as its payment is accepted. To cancel it, write to us: if it has not shipped yet, we cancel it with the workshop and refund the amount to the card you used.',

    'terms.listings.heading' => 'Product listings',
    'terms.listings.body' => 'Every listing is checked before it goes live, but the description and photographs are the workshop\'s own. If a piece you received does not match its listing, contact us.',

    'terms.reviews.heading' => 'Reviews',
    'terms.reviews.body' => 'After an order is delivered, we may invite you to leave a review. A review should be about your own experience and honest — we reserve the right to remove one that plainly is not.',

    'terms.sellers.heading' => 'Selling on Hirfaty',
    'terms.sellers.body' => 'Opening a shop goes through a review before it is approved. A shop or a listing that breaks these terms can be suspended or taken down.',

    'terms.suspension.heading' => 'Account suspension',
    'terms.suspension.body' => 'An account used for fraud or to break these terms can be suspended. We will tell you and give you a chance to explain, except in a clear emergency.',

    'terms.changes.heading' => 'Changes',
    'terms.changes.body' => 'We may update these terms; the date at the top shows the last change. Continuing to use Hirfaty after a change means you accept the new version.',

    'terms.law.heading' => 'Governing law',
    'terms.law.body' => 'These terms are governed by Moroccan law. If something is wrong, write to us first — most things get sorted out without needing to go further.',

    'terms.contact.heading' => 'Getting in touch',
    'terms.contact.body' => 'Any question about these terms goes to the same address:',
];
