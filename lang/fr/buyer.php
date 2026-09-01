<?php

// Flat keys — dots are part of the key, NOT nested arrays. useTranslation()
// splits on the first dot only to pick the file, then does a flat lookup on
// the remainder, so 'products.title' resolves as t('buyer.products.title').
// All three locales (fr/ar/en) must define the same keys.

return [
    // ── Product listing ───────────────────────────────────────────
    'products.hero_title_l1' => 'Authentique.',
    'products.hero_title_l2' => 'Fait main.',
    'products.hero_title_l3' => 'Fait avec cœur.',
    'products.hero_subtitle' => 'Découvrez des pièces uniques, façonnées par des mains talentueuses.',
    'products.feature_handmade' => 'Fait main',
    'products.feature_handmade_sub' => 'avec soin',
    'products.feature_natural' => 'Matières',
    'products.feature_natural_sub' => 'naturelles',
    'products.feature_unique' => 'Unique',
    'products.feature_unique_sub' => '& authentique',
    'products.feature_artisans' => 'Soutenir',
    'products.feature_artisans_sub' => 'les artisans',
    'products.hero_slide_pottery' => 'Potier marocain tournant une pièce dans son atelier',
    'products.hero_slide_textile' => 'Tissage de laine sur un métier traditionnel',
    'products.hero_slide_zellige' => 'Zellige taillé à la main, motif géométrique',
    'products.hero_slide_spice' => 'Épices marocaines présentées au souk',

    'products.search_placeholder' => 'Rechercher une pièce, un atelier…',
    'products.search_label' => 'Rechercher un produit',
    'products.search_clear' => 'Effacer la recherche',

    'products.refine' => 'Affiner',
    'products.refine_open' => 'Ouvrir',
    'products.sort' => 'Trier',
    'products.sort_score' => 'Pertinence',
    'products.sort_latest' => 'Nouveautés',
    'products.sort_price_asc' => 'Prix croissant',
    'products.sort_price_desc' => 'Prix décroissant',
    'products.sort_rating' => 'Mieux notés',

    'products.price' => 'Prix',
    'products.price_max_label' => 'Prix maximum',
    'products.in_stock_only' => 'En stock uniquement',
    'products.reset_all' => 'Tout réinitialiser',

    'products.badge_sale' => 'Promo',
    'products.badge_sold_out' => 'Épuisé',
    'products.no_image' => 'Sans image',

    'products.empty_with_search' => 'Aucune pièce ne correspond à votre recherche.',
    'products.empty' => 'Aucune pièce ne correspond à vos filtres.',

    'products.load_more' => 'Voir plus de pièces',
    'products.loading' => 'Chargement…',
    'products.count_of' => 'sur',

    // ── Professional / trade pricing ──────────────────────────────
    'products.pro_short' => 'Pro*',
    'products.pro_from_qty' => 'dès',
    'products.pro_price_hint' => 'Tarif professionnel indicatif — la facturation reste au prix public pour le moment.',
    'products.pro_notice_title' => 'Compte professionnel.',
    'products.pro_notice_body' => 'Vos tarifs pro apparaissent sous le prix public. Ils sont indicatifs : la facturation reste au prix public pour le moment.',
    'products.pro_cta_body' => 'Vous achetez pour un riad, un hôtel ou une boutique ? Des tarifs professionnels existent sur une grande partie du catalogue.',
    'products.pro_cta_button' => 'Ouvrir un compte pro',

    // ── Product detail ────────────────────────────────────────────
    'detail.back_to_market' => 'Le marché',
    'detail.add_to_cart' => 'Ajouter au panier',
    'detail.go_to_cart' => 'Voir le panier',
    'detail.sold_out' => 'Épuisé',
    'detail.in_stock' => 'En stock',
    'detail.only_left' => 'Plus que :count en stock',
    'detail.quantity' => 'Quantité',
    'detail.decrease' => 'Diminuer',
    'detail.increase' => 'Augmenter',
    'detail.wishlist_add' => 'Ajouter aux favoris',
    'detail.wishlist_remove' => 'Retirer des favoris',
    'detail.description' => 'Description',
    'detail.no_description' => 'Aucune description fournie.',
    'detail.delivery' => 'Livraison Amana sous 3 à 7 jours partout au Maroc.',
    'detail.authentic' => 'Pièce authentique, garantie de l’atelier.',
    'detail.view_media' => 'Voir le média :n',

    // Seller card
    'detail.sold_by' => 'Vendu par',
    'detail.visit_store' => 'Voir la boutique',
    'detail.more_from_store' => 'Autres pièces de l’atelier',

    // Pricing
    'detail.public_price' => 'Prix public',
    'detail.your_trade_price' => 'Votre prix professionnel',
    'detail.trade_saving' => 'soit :percent% d’économie',
    'detail.trade_min_qty' => 'Commande minimum : :count pièces',
    'detail.trade_billed_note' => 'Ce tarif est appliqué automatiquement au paiement.',
    'detail.trade_cta_title' => 'Vous achetez en gros ?',
    'detail.trade_cta_body' => 'Les comptes professionnels vérifiés bénéficient de tarifs réservés sur une grande partie du catalogue.',
    'detail.trade_cta_button' => 'Ouvrir un compte pro',
    'detail.trade_pending_title' => 'Compte professionnel en attente de vérification.',
    'detail.trade_pending_body' => 'Vos tarifs professionnels s’appliqueront dès qu’un administrateur aura validé votre compte.',
];
