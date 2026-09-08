<?php

// Flat keys — dots are part of the key, NOT nested arrays. Keep in sync with
// lang/en/pro.php and lang/ar/pro.php.
//
// Copy de la page publique « compte professionnel » (/pro). Tout est écrit
// d'après ce que fait réellement le code : Product::audienceFor() réserve la
// liste de gros aux comptes professionnels *vérifiés*, CheckoutService
// facture cette liste, et CartService applique min_order_qty_professional.

return [
    'meta.title' => 'Comptes professionnels',

    'hero.eyebrow' => 'Pour les professionnels',
    'hero.title1' => 'Vous achetez pour un riad,',
    'hero.title2' => 'un hôtel, une boutique ?',
    'hero.lead' => 'Un compte professionnel vous ouvre des tarifs de gros réservés sur une grande partie du catalogue — achetés directement à l’artisan qui a façonné la pièce, comme tout le monde.',
    'hero.cta_guest' => 'Créer un compte',
    'hero.cta_buyer' => 'Demander l’accès pro',
    'hero.cta_pending' => 'Votre demande est en cours d’examen',
    'hero.cta_verified' => 'Votre compte pro est actif',
    'hero.browse' => 'Parcourir le catalogue',

    // ── Ce que ça apporte ────────────────────────────────────────────────
    'perks.eyebrow' => 'Ce que ça vous apporte',
    'perks.heading' => 'Le gros, sans intermédiaire',

    'perks.pricing.title' => 'Votre propre grille tarifaire',
    'perks.pricing.body' => 'Les comptes professionnels vérifiés voient un tarif réservé sur chaque pièce pour laquelle l’atelier en propose un. Il s’affiche sur la fiche produit comme votre prix — et c’est bien ce montant que le panier totalise et que le paiement facture, pas une indication.',

    'perks.catalogue.title' => 'Des pièces réservées au métier',
    'perks.catalogue.body' => 'Certains ateliers proposent des pièces aux professionnels uniquement. Elles apparaissent dans votre catalogue et dans aucun autre.',

    'perks.direct.title' => 'Directement de l’atelier',
    'perks.direct.body' => 'Vous commandez à l’artisan, pas à un revendeur. Les mêmes mains, les mêmes pièces — seule la grille tarifaire change.',

    // ── Ce que vous voyez vraiment (la démo visuelle) ────────────────────
    'demo.eyebrow' => 'Voyez la différence',
    'demo.heading' => 'La même pièce, deux grilles',
    'demo.lead' => 'Rien ne change dans le catalogue. Les pièces, les ateliers et les photographies sont identiques — seul le montant sur la fiche est le vôtre.',
    'demo.public_label' => 'Ce que tout le monde voit',
    'demo.trade_label' => 'Ce que vous voyez',
    'demo.product_name' => 'Tajine émaillé, Safi',
    'demo.workshop' => 'Atelier Safi · Safi',
    'demo.badge' => 'Pro',
    'demo.saving' => 'Vous économisez :percent%',
    'demo.min_note' => 'Dès :count pièces',
    'demo.disclaimer' => 'Illustration — les montants affichés sont un exemple, pas une offre réelle.',

    // ── Comment ça marche ────────────────────────────────────────────────
    'steps.eyebrow' => 'Comment ça marche',
    'steps.heading' => 'Trois étapes',

    'steps.1.title' => 'Créez votre compte',
    'steps.1.body' => 'Un compte acheteur ordinaire, gratuit, en une minute. Vous pouvez parcourir et commander au prix public immédiatement.',

    'steps.2.title' => 'Parlez-nous de votre activité',
    'steps.2.body' => 'Quelques mots sur ce que vous faites et ce que vous achetez en quantité — un riad qui meuble ses chambres, une boutique qui se réapprovisionne, un restaurant qui dresse ses tables.',

    'steps.3.title' => 'Nous examinons et revenons vers vous',
    'steps.3.body' => 'Une personne lit chaque demande. Vous êtes prévenu par email dans les deux cas, et vos tarifs s’appliquent dès l’activation du compte.',

    // ── Bon à savoir ─────────────────────────────────────────────────────
    'notes.eyebrow' => 'Bon à savoir',
    'notes.heading' => 'Les conditions, simplement',

    'notes.review.title' => 'L’activation n’est pas automatique',
    'notes.review.body' => 'Les tarifs de gros supposent que vous achetez en tant que professionnel : un administrateur examine donc chaque demande, plutôt qu’un compte qui s’accorderait le tarif lui-même. En attendant, vous payez le prix public — rien ne se bloque entre-temps.',

    'notes.minimum.title' => 'Certaines pièces ont une commande minimum',
    'notes.minimum.body' => 'Un tarif de gros suppose que le métier achète en quantité : un atelier peut donc fixer un minimum sur une pièce. Quand il existe, il est indiqué sur la fiche produit, et le panier s’y tient.',

    'notes.same.title' => 'Tout le reste ne change pas',
    'notes.same.body' => 'Même livraison, mêmes ateliers, même historique de commandes. Un compte professionnel change la grille tarifaire dont vous dépendez — rien d’autre.',

    // ── Conclusion ───────────────────────────────────────────────────────
    'cta.heading' => 'Quand vous voulez',
    'cta.body' => 'Parlez-nous de votre activité et nous revenons vers vous rapidement.',
];
