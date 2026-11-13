<?php

// Flat keys — dots are part of the key, NOT nested arrays. Keep in sync with
// lang/en/pro.php and lang/ar/pro.php.
//
// Prix particuliers ou prix pro (PriceModeService). Écrit d'après ce que fait
// le code : n'importe quel visiteur choisit sa grille (popup à la première
// visite, sélecteur dans le menu), le paiement facture cette grille, et en
// mode pro CartService / CheckoutService imposent la quantité minimum fixée
// par pièce (min_order_qty_professional). Aucune demande, aucune validation.

return [
    // ── Page publique « prix professionnels » (/professional-account) ────
    'meta.title' => 'Prix professionnels',

    'hero.eyebrow' => 'Pour les professionnels',
    'hero.title1' => 'Vous achetez pour un riad,',
    'hero.title2' => 'un hôtel, une boutique ?',
    'hero.lead' => 'Passez en mode « Entreprise » et achetez au prix de gros, directement aux artisans. Aucune demande, aucune validation : la seule condition est une quantité minimum par pièce.',
    'hero.cta' => 'Voir les prix pro',
    'hero.cta_active' => 'Les prix pro sont activés',
    'hero.browse' => 'Parcourir le catalogue',

    // ── Ce que ça apporte ────────────────────────────────────────────────
    'perks.eyebrow' => 'Ce que ça vous apporte',
    'perks.heading' => 'Le gros, sans intermédiaire',

    'perks.pricing.title' => 'Des tarifs de gros',
    'perks.pricing.body' => 'En mode « Entreprise », chaque pièce pour laquelle l’atelier propose un tarif de gros s’affiche à ce prix — et c’est bien ce montant que le panier totalise et que le paiement facture.',

    'perks.catalogue.title' => 'Des pièces réservées au métier',
    'perks.catalogue.body' => 'Certains ateliers proposent des pièces aux professionnels uniquement. Elles apparaissent dans le catalogue dès que vous passez en mode « Entreprise ».',

    'perks.direct.title' => 'Directement de l’atelier',
    'perks.direct.body' => 'Vous commandez à l’artisan, pas à un revendeur. Les mêmes mains, les mêmes pièces — seule la grille tarifaire change.',

    // ── La démo visuelle ─────────────────────────────────────────────────
    'demo.eyebrow' => 'Voyez la différence',
    'demo.heading' => 'La même pièce, deux grilles',
    'demo.lead' => 'Rien ne change dans le catalogue. Les pièces, les ateliers et les photographies sont identiques — seul le prix change, à partir d’une quantité minimum.',
    'demo.public_label' => 'En mode « Particulier »',
    'demo.trade_label' => 'En mode « Entreprise »',
    'demo.product_name' => 'Tajine émaillé, Safi',
    'demo.workshop' => 'Atelier Safi · Safi',
    'demo.badge' => 'Pro',
    'demo.saving' => 'Vous économisez :percent%',
    'demo.min_note' => 'Dès :count pièces',
    'demo.disclaimer' => 'Illustration — les montants affichés sont un exemple, pas une offre réelle.',

    // ── Comment ça marche ────────────────────────────────────────────────
    'steps.eyebrow' => 'Comment ça marche',
    'steps.heading' => 'Trois étapes, aucune demande',

    'steps.1.title' => 'Passez en mode « Entreprise »',
    'steps.1.body' => 'Depuis le menu en haut de chaque page, ou dès votre première visite. Vous pouvez revenir aux prix particuliers à tout moment.',

    'steps.2.title' => 'Choisissez vos quantités',
    'steps.2.body' => 'Chaque pièce indique sur sa fiche sa quantité minimum au prix pro. Le panier s’y tient tout seul.',

    'steps.3.title' => 'Commandez au prix pro',
    'steps.3.body' => 'Le paiement facture le prix pro de chaque pièce. Même livraison, mêmes ateliers que pour tout le monde.',

    // ── Bon à savoir ─────────────────────────────────────────────────────
    'notes.eyebrow' => 'Bon à savoir',
    'notes.heading' => 'Les conditions, simplement',

    'notes.open.title' => 'Aucune demande, aucune attente',
    'notes.open.body' => 'Pas de compte professionnel à demander ni de validation à attendre : le prix pro s’applique dès que vous passez en mode « Entreprise ».',

    'notes.minimum.title' => 'Une quantité minimum par pièce',
    'notes.minimum.body' => 'Un tarif de gros suppose une commande en quantité : chaque pièce peut avoir un minimum au prix pro, fixé par notre équipe. Il est indiqué sur la fiche produit, et le panier s’y tient.',

    'notes.switch.title' => 'Moins de pièces ? Prix particulier',
    'notes.switch.body' => 'Besoin d’une seule pièce ? Revenez en mode « Particulier » : le prix public s’applique, dès une pièce.',

    // ── Conclusion ───────────────────────────────────────────────────────
    'cta.heading' => 'Prêt à commander ?',
    'cta.body' => 'Passez en mode « Entreprise » et retrouvez tout le catalogue au prix de gros.',
    'cta.active_heading' => 'Vous êtes en mode « Entreprise »',
    'cta.active_body' => 'Les prix de gros s’affichent sur tout le catalogue. Il ne reste qu’à choisir vos pièces.',

    // ── Le sélecteur particulier / pro ───────────────────────────────────
    'mode.label' => 'Prix affichés',
    'mode.aria' => 'Afficher les prix particuliers ou professionnels',
    'mode.individual' => 'Particulier',
    'mode.professional' => 'Entreprise',
    'mode.individual_hint' => 'Prix publics, dès une pièce',
    'mode.professional_hint' => 'Prix de gros, quantité minimum',

    // Le choix posé à la première visite.
    'chooser.title' => 'Comment souhaitez-vous acheter ?',
    'chooser.lead' => 'Choisissez votre grille de prix. Vous pourrez la changer à tout moment.',
    'chooser.professional.title' => 'J’achète pour mon activité',
    'chooser.professional.hint' => 'Prix professionnels',
    'chooser.individual.title' => 'J’achète pour moi',
    'chooser.individual.hint' => 'Prix particuliers',
    'chooser.footnote' => 'Aucune obligation : changez quand vous voulez depuis le menu.',
    'chooser.close' => 'Fermer',

    // Bandeau au-dessus des grilles en mode pro.
    'notice.title' => 'Prix pro affichés.',
    'notice.body' => 'Chaque pièce se commande à partir de sa quantité minimum au prix pro, indiquée sur sa fiche.',
    'notice.show_retail' => 'Voir les prix particuliers',

    // Prix sur les cartes et la fiche.
    'price.pro' => 'Prix pro',
    'price.tag' => 'Pro',
    'price.public' => 'Prix public',
    'price.no_pro_rate' => 'Pas de tarif pro sur cette pièce : c’est le prix public.',
    'price.see_pro' => 'Voir les prix pro',
    'price.min_qty' => 'Minimum :count pièces au tarif pro',
    'price.fewer' => 'Moins de :count pièces ?',
    'price.buy_retail' => 'Acheter au prix particulier',

    // ── Fiche produit en mode particulier : le même article au prix pro ──
    'offer.eyebrow' => 'Vous achetez pour votre activité ?',
    'offer.current' => 'Votre prix',
    'offer.personal' => 'Particulier',
    'offer.pro' => 'Professionnel',
    'offer.from_one' => 'Dès 1 pièce',
    'offer.from_qty' => 'Dès :count pièces',
    'offer.per_piece' => 'par pièce',
    'offer.saving' => 'Vous économisez :amount sur une commande de :count pièces.',
    'offer.cta' => 'Passer en mode Entreprise',
];
