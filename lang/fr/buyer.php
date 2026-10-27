<?php

// Flat keys — dots are part of the key, NOT nested arrays. useTranslation()
// splits on the first dot only to pick the file, then does a flat lookup on
// the remainder, so 'products.title' resolves as t('buyer.products.title').
// All three locales (fr/ar/en) must define the same keys.

return [
    // ── Product listing ───────────────────────────────────────────
    'products.hero_eyebrow' => 'Façonné lentement · Transmis de génération en génération',
    'products.hero_title' => 'Découvrez l’artisanat marocain',
    'products.hero_subtitle' => 'Des pièces faites main par des artisans marocains, qui portent le caractère de leur métier jusque chez vous.',

    'products.collection_eyebrow' => 'La collection complète',
    'products.collection_title' => 'Des objets qui ont une histoire',
    'products.category_all' => 'Tout',
    'products.categories' => 'Catégories',
    'products.count_pieces' => 'pièces faites main',
    'products.made_in_morocco' => 'Fabriqué au Maroc',

    'products.search_placeholder' => 'Rechercher un produit fait main…',
    'products.search_label' => 'Rechercher un produit',

    'products.reset_all' => 'Tout réinitialiser',

    'products.view_product' => 'Voir la pièce',
    'products.wishlist_add' => 'Ajouter à vos coups de cœur',
    'products.wishlist_remove' => 'Retirer de vos coups de cœur',

    'products.badge_sale' => 'Promo',
    'products.badge_sold_out' => 'Épuisé',
    'products.no_image' => 'Sans image',
    // Demo-only: an unpriced product is temporarily shown in the
    // storefront rather than hidden — see Product::scopeStorefront().
    'products.price_coming_soon' => 'Prix à venir',

    'products.empty_with_search' => 'Aucune pièce ne correspond à votre recherche.',
    'products.empty' => 'Aucune pièce ne correspond à vos filtres.',

    'products.load_more' => 'Voir plus de pièces',
    'products.loading' => 'Chargement…',

    'products.closing_title' => 'L’artisanat porte la mémoire.',
    'products.closing_subtitle' => 'Sourcé avec soin auprès d’ateliers indépendants partout au Maroc.',

    // ── Professional / trade pricing ──────────────────────────────
    'products.pro_short' => 'Pro*',
    'products.pro_from_qty' => 'dès',
    'products.pro_price_hint' => 'Votre tarif professionnel — c’est ce que le paiement facture.',

    // ── Nouveautés ────────────────────────────────────────────────
    'new.title' => 'Nouveautés',
    'new.eyebrow' => 'Tout juste en ligne',
    'new.heading' => 'Sorties d’atelier',
    'new.lead' => 'Les pièces les plus récemment mises en ligne par nos artisans, les plus nouvelles d’abord.',
    'new.browse_all' => 'Parcourir toute la collection',
    'new.empty' => 'Rien n’a encore été mis en ligne — revenez très bientôt.',

    // ── Historique des commandes ──────────────────────────────────
    'orders.status_pending' => 'En attente',
    'orders.status_confirmed' => 'Confirmée',
    'orders.status_shipped' => 'Expédiée',
    'orders.status_delivered' => 'Livrée',
    'orders.status_cancelled' => 'Annulée',

    // Une carte = une commande passée, qui peut couvrir plusieurs ateliers —
    // voir BuyerAccountService::purchases().
    'orders.shops_count' => ':count ateliers',
    'orders.items_total' => 'Pièces',
    'orders.delivery' => 'Livraison',
    'orders.total' => 'Total',

    // Carte de commande — pages/buyer/orders/index.tsx.
    'orders.number' => 'Commande n° :reference',
    'orders.products_one' => '1 article',
    'orders.products_many' => ':count articles',
    'orders.by' => 'Par :name',
    'orders.status' => 'Statut',
    'orders.delivery_date' => 'Date de livraison',
    'orders.delivery_pending' => 'À confirmer',
    'orders.delivered_to' => 'Livré à',
    'orders.quantity' => 'Quantité',
    'orders.variant' => 'Option',
    'orders.workshop' => 'Atelier',
    'orders.download_invoice' => 'Télécharger la facture',
    'orders.more_actions' => 'Plus d’actions',
    'orders.copy_reference' => 'Copier la référence',
    'orders.reference_copied' => 'Référence copiée',
    'orders.contact' => 'Contacter le service client',
    'orders.track' => 'Historique de la commande',
    'orders.rate' => 'Noter mes articles',
    'orders.my_reviews' => 'Voir mes avis',
    'orders.back' => 'Retour à mes commandes',

    'tracking.title' => 'Suivi de la commande',
    'tracking.intro' => 'Chaque étape de votre commande, de la confirmation à la livraison.',
    'tracking.step_placed' => 'Passée',
    'tracking.step_confirmed' => 'Confirmée',
    'tracking.step_shipped' => 'Expédiée',
    'tracking.step_delivered' => 'Livrée',
    'tracking.history' => 'Historique',
    'tracking.event_pending' => 'Commande reçue',
    'tracking.event_confirmed' => 'Commande confirmée auprès des artisans',
    'tracking.event_shipped' => 'Colis remis au transporteur',
    'tracking.event_delivered' => 'Colis livré',
    'tracking.event_cancelled' => 'Commande annulée',
    'tracking.event_update' => 'Mise à jour de l’acheminement',
    'tracking.carrier' => 'Transporteur',
    'tracking.tracking_number' => 'N° de suivi',
    'tracking.only' => 'Concerne : :names',
    'tracking.cancelled_notice' => 'Cette commande a été annulée.',

    'rate.title' => 'Noter mes articles',
    'rate.intro' => 'Une note et un avis pour chaque pièce reçue — cela aide les artisans et les autres acheteurs.',
    'rate.comment_placeholder' => 'Qu’avez-vous pensé de cette pièce ? Qualité, finitions, conformité aux photos…',
    'rate.not_delivered' => 'À noter une fois livrée',
    'rate.submit' => 'Envoyer mes avis',
    'rate.all_done' => 'Merci ! Toutes vos pièces livrées ont été notées.',

    // État vide — components/buyer/orders/EmptyOrders.tsx.
    'orders.empty_eyebrow' => 'Bienvenue',
    'orders.empty_title' => 'Prêt à magasiner ?',
    'orders.empty_cta' => 'Découvrir la boutique',

    // ── Évaluations ───────────────────────────────────────────────
    // Proposées uniquement sur une commande livrée, et une seule fois par
    // pièce — voir ReviewService. L’atelier lui-même n’est plus noté.
    'reviews.heading' => 'Comment cela s’est-il passé ?',
    'reviews.all_rated_heading' => 'Votre évaluation',
    'reviews.rate_product' => 'Noter cette pièce',
    'reviews.rated' => 'Notée',
    'reviews.star_label' => ':count sur 5',
    'reviews.comment_placeholder' => 'Quelque chose à ajouter ? (facultatif)',
    'reviews.final_notice' => 'Une note ne peut plus être modifiée une fois envoyée.',
    'reviews.submit' => 'Envoyer',
    'reviews.thanks' => 'Merci.',
    'reviews.already_rated' => 'Vous avez déjà noté cette pièce.',

    // La fenêtre proposée à l’arrivée quand une livraison reste à noter.
    'reviews.prompt_title' => 'Votre commande est arrivée',
    'reviews.prompt_body' => 'Dites-nous ce que vous pensez des pièces reçues. C’est l’affaire d’un instant, et cela aide l’artisan.',
    'reviews.prompt_body_plural' => 'Vous avez :count livraisons à noter. C’est l’affaire d’un instant, et cela aide les artisans.',
    'reviews.prompt_cta' => 'Noter ma commande',
    'reviews.prompt_later' => 'Plus tard',

    // ── Product detail ────────────────────────────────────────────
    'detail.back_to_market' => 'Le marché',
    'detail.add_to_cart' => 'Ajouter au panier',
    'detail.buy_now' => 'Acheter maintenant',
    'detail.go_to_cart' => 'Voir le panier',
    'detail.sold_out' => 'Rupture de stock',
    'detail.price_coming_soon' => 'Prix à venir',
    'detail.in_stock' => 'En stock',
    'detail.quantity' => 'Quantité',
    'detail.decrease' => 'Diminuer',
    'detail.increase' => 'Augmenter',
    'detail.wishlist_add' => 'Ajouter aux favoris',
    'detail.wishlist_remove' => 'Retirer des favoris',
    'detail.description' => 'Description',
    'detail.no_description' => 'Aucune description fournie.',
    'detail.delivery' => 'Livraison en 3 à 7 jours en moyenne au Maroc, 7 à 14 jours à l\'international.',
    'detail.authentic' => 'Pièce authentique, garantie de l’atelier.',
    'detail.view_media' => 'Voir le média :n',
    'detail.lightbox_open' => 'Agrandir la photo',
    'detail.lightbox_close' => 'Fermer',
    'detail.lightbox_previous' => 'Photo précédente',
    'detail.lightbox_next' => 'Photo suivante',

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

    // Variants
    'detail.select_variant_prompt' => 'Choisissez une option',

    // Sign-in gate
    'detail.login_to_buy' => 'Connectez-vous pour acheter',
    'detail.login_to_buy_hint' => 'Votre panier et vos prix suivent votre compte.',
    'detail.login_to_buy_register' => 'Créer un compte',
    'detail.below_min_qty' => 'Stock insuffisant pour votre commande minimum',
    'detail.below_min_stock' => 'Plus que :count en stock — votre minimum est de :min',

    // Add to cart feedback
    'detail.adding' => 'Ajouté au panier',
    'detail.added_to_cart' => 'Ajouté à votre panier',
    'detail.add_failed' => 'Impossible d’ajouter au panier.',
    'detail.buy_now_failed' => 'Impossible de passer à la commande pour cette pièce.',

    // Page furniture
    'detail.home' => 'Accueil',
    'detail.eyebrow_handmade' => 'Fait main',
    'detail.related' => 'Vous aimerez aussi',
    'detail.reviews_heading' => 'Avis des acheteurs',
    'detail.reviews_count_one' => ':n avis',
    'detail.reviews_count' => ':n avis',
    'detail.reviews_out_of' => 'sur 5',
    'detail.reviews_stars_label' => ':n sur 5 étoiles',
    'detail.reviews_star_row' => ':n étoiles',
    'detail.reviews_empty' => 'Pas encore d’avis sur cette pièce.',
    'detail.reviews_empty_hint' => 'Les avis sont laissés par les acheteurs une fois leur commande reçue.',
    'detail.reviews_verified' => 'Achat vérifié',
    'detail.reviews_anonymous' => 'Acheteur Hirfaty',
    'detail.reviews_more' => 'Voir plus d’avis',
    'detail.my_review_title' => 'Vous avez reçu cette pièce',
    'detail.my_review_intro' => 'Qu’en avez-vous pensé ? Votre note aide l’artisan et les autres acheteurs.',
    'detail.handmade_note' => 'Chaque pièce est faite main : de légères variations de teinte et de motif sont possibles, et rendent la vôtre unique.',
    'detail.specs_heading' => 'Poids et dimensions (emballé)',
    'detail.specs_weight' => 'Poids',
    'detail.specs_dimensions' => 'Dimensions (L × l × H)',
    'detail.specs_length' => 'Longueur',
    'detail.specs_width' => 'Largeur',
    'detail.specs_height' => 'Hauteur',

    // Reassurance row
    'detail.trust_materials_title' => 'Matières naturelles',
    'detail.trust_materials_body' => 'Argile et émaux naturels de qualité.',
    'detail.trust_handmade_title' => 'Fait main',
    'detail.trust_handmade_body' => 'Chaque pièce est façonnée à la main.',
    'detail.trust_durable_title' => 'Durable',
    'detail.trust_durable_body' => 'Cuisson à haute température, pour durer.',
    'detail.trust_packaging_title' => 'Emballage soigné',
    'detail.trust_packaging_body' => 'Emballé avec soin pour voyager sans risque.',

    // ── Onboarding (première visite d'un acheteur) ───────────────────────
    'onboarding.title' => 'Bienvenue',
    'onboarding.skip' => 'Passer',
    'onboarding.back' => 'Retour',
    'onboarding.next' => 'Continuer',
    'onboarding.finish' => 'Découvrir le marché',

    'onboarding.welcome.title' => 'Bienvenue, :name',
    'onboarding.welcome.title_generic' => 'Bienvenue',
    'onboarding.welcome.body' => 'Hirfaty rassemble les artisans du Maroc en un seul endroit. Deux minutes pour faire connaissance, puis le marché est à vous.',
    'onboarding.welcome.point_direct' => 'Vous achetez directement à l\'artisan — sans intermédiaire.',
    'onboarding.welcome.point_authentic' => 'Chaque pièce est faite main, chaque boutique est vérifiée par notre équipe.',
    'onboarding.welcome.point_delivery' => 'Livraison partout au Maroc, suivie depuis votre espace commandes.',

    'onboarding.interests.title' => 'Qu\'est-ce qui vous attire ?',
    'onboarding.interests.body' => 'Choisissez les métiers qui vous intéressent. Nous nous en servirons pour mettre en avant ce qui vous ressemble.',
    'onboarding.interests.optional' => 'Facultatif — vous pourrez changer d\'avis à tout moment.',

    /*
     * `.placeholder` et `.hint` sont les deux seules clés restantes de
     * l'ancienne étape professionnelle : le parcours ne la pose plus, mais
     * pages/buyer/professional-account.tsx les réutilise pour la même
     * question posée plus tard, quand un acheteur la demande réellement. Ne
     * pas supprimer.
     */
    'onboarding.professional.placeholder' => 'Ex : Je gère un riad de 8 chambres à Marrakech et je cherche du linge de maison et de la vaisselle artisanale en quantité.',
    'onboarding.professional.hint' => '20 caractères minimum. Vous pourrez continuer à commander normalement pendant l\'examen de votre demande.',

    // ── Compte professionnel (page dédiée) ───────────────────────────────
    'professional.title' => 'Compte professionnel',
    'professional.intro' => 'Riad, hôtel, restaurant, boutique : si vous achetez en quantité, demandez l\'accès aux tarifs de gros.',
    'professional.label' => 'Parlez-nous de votre activité',
    'professional.submit' => 'Envoyer ma demande',
    'professional.pending_title' => 'Demande en cours d\'examen',
    'professional.pending_body' => 'Notre équipe étudie votre demande. Vous recevrez un email dès qu\'elle sera traitée — vous pouvez continuer à commander normalement en attendant.',
    'professional.approved_title' => 'Compte professionnel actif',
    'professional.approved_body' => 'Les tarifs de gros s\'appliquent automatiquement sur les produits concernés.',
    'professional.declined_title' => 'Demande non retenue',
    'professional.declined_reason' => 'Motif',
    'professional.declined_retry' => 'Vous pouvez soumettre une nouvelle demande.',
    'professional.submitted_on' => 'Envoyée le :date',

    // ── Espace compte : quatre pages partageant la barre latérale ─────────
    'account.title' => 'Mon compte',
    'account.subtitle' => 'Vos informations, vos commandes et les pièces que vous avez enregistrées.',
    'account.preferences_title' => 'Langue et devise',
    'account.preferences_subtitle' => 'La façon dont le site s’affiche pour vous. Détectée depuis votre région, sauf si vous choisissez.',
    'account.currency_auto' => 'Les prix sont affichés en dirhams (MAD), la devise dans laquelle les commandes sont facturées.',

    'account.nav_overview' => 'Mon compte',
    'account.nav_orders' => 'Commandes',
    'account.nav_wishlist' => 'Coups de cœur',
    'account.nav_payment' => 'Paiement',
    /*
     * Toujours utilisée par le titre de la page /professional-account. La
     * barre d'onglets du compte ne la liste plus — voir la note dans
     * layouts/buyer/account-layout.tsx.
     */
    'account.nav_professional' => 'Compte professionnel',
    'account.logout' => 'Déconnexion',

    // Le type de compte — remplace l'avatar et le nom qui occupaient le haut
    // de la colonne latérale.
    'account.badge_personal' => 'Compte particulier',
    'account.badge_professional' => 'Compte professionnel',

    // Une carte par achat depuis que l'historique les regroupe — la
    // répartition entre ateliers apparaît dans la carte, pas en cartes de plus.
    'account.orders_intro' => 'Tout ce que vous avez commandé. Un achat qui touche plusieurs ateliers reste une seule commande ici, chaque atelier étant suivi séparément.',
    'account.wishlist_intro' => 'Les pièces que vous avez enregistrées, conservées jusqu\'à ce que vous les retiriez.',
    'account.payment_intro' => 'Les cartes et moyens utilisés pour régler vos commandes.',
    'account.professional_intro' => 'Demandez l\'accès professionnel, ou consultez l\'état d\'une demande déjà envoyée.',

    'account.placeholder' => 'Rien à afficher ici pour le moment.',

    // ── /account : identité, mot de passe, adresse par défaut ─────────────
    'account.section_identity' => 'Informations personnelles',
    'account.section_identity_desc' => 'Le nom au nom duquel vos commandes et bons de livraison sont établis.',
    'account.section_password' => 'Mot de passe',
    'account.section_password_desc' => 'Choisissez un nouveau mot de passe. Vous resterez connecté sur cet appareil.',
    'account.section_address' => 'Adresse de livraison par défaut',
    'account.section_address_desc' => 'Où vos commandes sont envoyées. Le paiement la renseigne pour vous.',

    'account.field_full_name' => 'Nom complet',
    'account.field_full_name_empty' => 'Ajoutez votre nom',
    'account.field_first_name' => 'Prénom',
    'account.field_last_name' => 'Nom',
    'account.field_email' => 'Adresse e-mail',
    'account.field_phone' => 'Téléphone',
    'account.locked_note' => 'Votre adresse e-mail et votre téléphone ne sont pas modifiables ici pour le moment — contactez-nous et nous les mettrons à jour.',

    'account.field_password' => 'Mot de passe',
    'account.change_password' => 'Changer le mot de passe',
    'account.field_current_password' => 'Mot de passe actuel',
    'account.field_new_password' => 'Nouveau mot de passe',
    'account.field_confirm_password' => 'Confirmer le nouveau mot de passe',

    'account.no_address' => 'Aucune adresse enregistrée.',
    'account.add_address' => 'Ajouter une adresse',
    'account.field_address_label' => 'Nom de l\'adresse',
    'account.field_address_label_hint' => 'Facultatif — pour vous y retrouver.',
    'account.field_address_label_ph' => 'Maison, Riad, Boutique…',
    'account.field_city' => 'Ville',
    'account.field_city_ph' => 'Choisissez une ville',
    'account.field_address_line' => 'Adresse',
    'account.field_address_line_ph' => 'N° 12, rue des Potiers, quartier…',
    'account.field_recipient' => 'Destinataire',
    'account.field_recipient_phone' => 'Téléphone du destinataire',

    'account.saved' => 'Enregistré.',
    'account.update_password' => 'Mettre à jour le mot de passe',
    'account.password_saved' => 'Mot de passe mis à jour.',
    'account.save_address' => 'Enregistrer l\'adresse',

    'account.profile_saved' => 'Vos informations ont été mises à jour.',
    'account.address_saved' => 'Votre adresse de livraison a été enregistrée.',
    'account.address_default_saved' => 'Adresse de livraison par défaut mise à jour.',
    'account.address_deleted' => 'Adresse supprimée.',

    'account.err_first_name' => 'Indiquez votre prénom.',
    'account.err_last_name' => 'Indiquez votre nom.',
    'account.err_address_line' => 'Indiquez l\'adresse de livraison.',
    'account.err_phone' => 'Indiquez un numéro de téléphone pour la livraison.',
    'account.err_city' => 'Choisissez une ville.',

    // ── Coups de cœur ────────────────────────────────────────────────────
    'wishlist.title' => 'Mes coups de cœur',

    // État vide — partage components/buyer/EmptyShowcase.tsx avec les commandes.
    'wishlist.empty_eyebrow' => 'Rien d’enregistré pour l’instant',
    'wishlist.empty_title' => 'Une pièce vous plaît ?',
    'wishlist.empty_cta' => 'Parcourir le marché',

    // ── Panier ───────────────────────────────────────────────────────────
    'cart.title' => 'Votre panier',
    'cart.back' => 'Continuer la découverte',
    'cart.count_one' => ':count article',
    'cart.count_many' => ':count articles',

    'cart.empty_title' => 'Votre panier est vide',
    'cart.empty_body' => 'Parcourez le marché et laissez-vous surprendre par le travail de nos artisans.',
    'cart.empty_cta' => 'Découvrir le marché',

    'cart.sold_by' => 'par :store',
    'cart.remove' => 'Retirer',
    'cart.decrease' => 'Diminuer la quantité',
    'cart.increase' => 'Augmenter la quantité',
    'cart.per_unit' => 'la pièce',
    'cart.stock_left' => 'Plus que :count en stock',
    'cart.min_qty' => 'Commande minimum : :count',
    'cart.unpriced' => 'Prix à confirmer — retirez cet article pour continuer.',

    'cart.summary' => 'Résumé',
    'cart.subtotal' => 'Sous-total',
    'cart.savings' => 'Économies',
    'cart.total' => 'Total',
    'cart.checkout' => 'Passer commande',
    'cart.secure_payment' => 'Paiement sécurisé',
    'cart.split_note' => 'Votre panier sera réparti en une commande par atelier.',

    'cart.you_might_also_like' => 'Vous aimerez aussi',

    // ── Commande ─────────────────────────────────────────────────────────
    'checkout.title' => 'Finaliser la commande',
    'checkout.back' => 'Retour au panier',
    'checkout.back_to_product' => 'Retour à la pièce',

    'checkout.delivery_heading' => 'Livraison',
    'checkout.saved_addresses' => 'Vos adresses enregistrées',
    'checkout.default_badge' => 'Par défaut',
    'checkout.use_new_address' => 'Utiliser une autre adresse',
    'checkout.new_address' => 'Nouvelle adresse',
    'checkout.city' => 'Ville',
    'checkout.address_line' => 'Adresse complète',
    'checkout.address_placeholder' => 'Rue, quartier, numéro de maison…',
    'checkout.phone' => 'Téléphone',
    'checkout.phone_hint' => 'Le livreur vous appellera à ce numéro.',
    'checkout.phone_placeholder' => '06 12 34 56 78',
    'checkout.save_address' => 'Enregistrer cette adresse dans mon carnet',
    'checkout.address_label' => 'Nom de l’adresse',
    'checkout.address_label_placeholder' => 'Maison, bureau…',
    'checkout.notes' => 'Notes',
    'checkout.optional' => 'facultatif',
    'checkout.notes_placeholder' => 'Instructions pour le livreur…',

    'checkout.shipments_heading' => 'Détail des expéditions',
    'checkout.shipments_note_one' => 'Votre panier vient d’un seul atelier : vous recevrez un colis.',
    'checkout.shipments_note_many' => 'Votre panier vient de :count ateliers : vous recevrez :count colis distincts.',
    'checkout.shipped_by' => 'Expédié par :store',
    'checkout.quantity' => 'Quantité : :count',

    'checkout.summary' => 'Récapitulatif',
    'checkout.products' => 'Produits (:count)',
    'checkout.delivery_fee' => 'Frais de livraison',
    'checkout.delivery_service_heading' => 'Mode de livraison',
    'checkout.total' => 'Total',
    'checkout.processing' => 'Traitement en cours…',

    'checkout.err_address' => 'Indiquez une adresse de livraison.',
    'checkout.err_city' => 'Choisissez une ville.',
    'checkout.err_phone' => 'Un numéro de téléphone est requis pour la livraison.',
    'checkout.err_empty' => 'Votre panier est vide.',
    'checkout.err_buy_now_gone' => 'Cette pièce n’est plus disponible. Retournez sur sa page pour réessayer.',
    'checkout.customer_heading' => 'Vos informations',
    'checkout.first_name' => 'Prénom',
    'checkout.first_name_placeholder' => 'Sarah',
    'checkout.last_name' => 'Nom',
    'checkout.last_name_placeholder' => 'Bennani',
    'checkout.email' => 'E-mail',
    'checkout.shipping_heading' => 'Adresse de livraison',
    'checkout.postal_code' => 'Code postal',
    'checkout.country' => 'Pays',
    'checkout.country_ma' => 'Maroc',
    'checkout.payment_heading' => 'Mode de paiement',
    'checkout.payment_card' => 'Carte bancaire',
    'checkout.card_note' => 'Paiement sécurisé 3D Secure par Payzone (Visa, Mastercard). Vous êtes redirigé vers la page de paiement, puis vous revenez ici. Le montant est débité en dirhams (MAD).',
    'checkout.payment_paypal' => 'PayPal',
    'checkout.paypal_note' => 'Payez avec votre compte PayPal, ou par carte via PayPal. Vous êtes redirigé vers PayPal, puis vous revenez ici.',
    'checkout.paypal_charged' => 'PayPal ne prend pas le dirham : vous serez débité de :amount.',
    'checkout.payment_unavailable' => 'Le paiement en ligne est momentanément indisponible. Réessayez un peu plus tard.',
    'checkout.confirm_card' => 'Continuer vers le paiement',
    'checkout.confirm_paypal' => 'Continuer avec PayPal',
    'checkout.coupon' => 'Code promo',
    'checkout.coupon_placeholder' => 'BIENVENUE10',
    'checkout.coupon_apply' => 'Appliquer',
    'checkout.err_first_name' => 'Indiquez votre prénom.',
    'checkout.err_last_name' => 'Indiquez votre nom.',
    'checkout.err_postal_code' => 'Indiquez un code postal.',
    'checkout.err_country' => 'Nous ne livrons pas encore dans ce pays.',
    'checkout.err_delivery_service' => 'Ce mode de livraison n’est pas disponible pour cette destination.',
    'checkout.err_payment_method' => 'Choisissez un mode de paiement.',
    'checkout.err_coupon_unknown' => 'Ce code promo n’est pas valide.',
    'order_placed.eyebrow' => 'Félicitations',
    'order_placed.title' => 'Votre commande est passée !',
    'order_placed.body' => 'Merci pour votre confiance. Nos artisans se mettent au travail et nous vous tiendrons informé(e) par e-mail à chaque étape.',
    'order_placed.reference' => 'Référence',
    'order_placed.email' => 'Un récapitulatif a été envoyé à :email.',
    'order_placed.view' => 'Voir mes commandes',
    'order_placed.continue' => 'Continuer mes achats',
    'order_placed.summary' => 'Récapitulatif',
    'order_placed.next_title' => 'Et maintenant ?',
    'order_placed.step_confirm' => 'Votre commande est confirmée dès le paiement accepté — les artisans préparent vos pièces.',
    'order_placed.step_ship' => 'Vos pièces sont préparées puis expédiées ; un e-mail vous prévient au départ du colis.',
    'order_placed.step_deliver' => 'Le livreur vous remet votre colis — déjà payé, rien à régler à la porte. Vous pouvez ensuite noter chaque pièce depuis vos commandes.',

    // Facture PDF — resources/views/invoices/purchase.blade.php.
    'invoice.title' => 'Facture',
    'invoice.number' => 'N°',
    'invoice.date' => 'Date :',
    'invoice.billed_to' => 'Facturé à',
    'invoice.delivered_to' => 'Livré à',
    'invoice.carrier' => 'Transporteur :',
    'invoice.item' => 'Article',
    'invoice.quantity' => 'Qté',
    'invoice.unit_price' => 'Prix unitaire',
    'invoice.subtotal' => 'Sous-total',
    'invoice.workshop' => 'Atelier :',
    'invoice.items_total' => 'Total articles',
    'invoice.delivery' => 'Livraison',
    'invoice.total' => 'Total TTC',
    'invoice.payment' => 'Mode de paiement : paiement à la livraison.',
    'invoice.payment_card' => 'Mode de paiement : carte bancaire (Payzone), réglée en ligne.',
    'invoice.payment_paypal' => 'Mode de paiement : PayPal, réglé en ligne.',
    'invoice.footer' => 'Merci de soutenir l’artisanat marocain — hirfaty.com',
    'invoice.filename' => 'facture-hirfaty-:reference',

    // ── Page boutique ────────────────────────────────────────────────────
    'store.back_to_market' => 'Le marché',
    'store.type_artist' => 'Artisan',
    'store.type_company' => 'Entreprise',
    'store.type_cooperative' => 'Coopérative',
    'store.since' => 'Sur Hirfaty depuis :date',
    // La marque de confiance à côté du nom de l'atelier, sur la fiche produit
    // et sur la page de l'atelier. Reflète la validation admin que l'atelier a
    // dû obtenir pour pouvoir vendre.
    'store.verified' => 'Vérifié par Hirfaty',
    'store.count_one' => ':count pièce',
    'store.count_many' => ':count pièces',
    'store.creations' => 'Les créations de l’atelier',
    'store.empty_title' => 'Aucune pièce en ligne',
    'store.empty_body' => 'Cet atelier n’a pas encore de pièce publiée. Revenez bientôt.',
    'store.empty_cta' => 'Explorer le marché',
    'store.load_more' => 'Voir plus de pièces',
    'store.loading' => 'Chargement…',
    'orders.payment' => 'Paiement',
    'orders.payment_card' => 'Carte bancaire — payée',
    'orders.payment_paypal' => 'PayPal — payé',
    'payment.redirect_title' => 'Paiement sécurisé',
    'payment.redirecting' => 'Redirection vers la page de paiement sécurisée…',
    'payment.redirecting_note' => 'Ne fermez pas cette fenêtre.',
    'payment.continue' => 'Continuer vers le paiement',
    'payment.title' => 'Paiement de votre commande',
    'payment.reference' => 'Commande',
    'payment.amount' => 'Montant',
    'payment.awaiting_title' => 'Nous confirmons votre paiement…',
    'payment.awaiting_body' => 'Payzone nous transmet le résultat de votre paiement. Cela ne prend généralement que quelques secondes — cette page se met à jour toute seule.',
    'payment.awaiting_body_paypal' => 'PayPal nous confirme votre paiement. Cela ne prend généralement que quelques secondes — cette page se met à jour toute seule.',
    'payment.slow_body' => 'La confirmation tarde un peu. Si votre carte a été débitée, votre commande sera validée automatiquement et vous recevrez un e-mail. Sinon, vous pouvez réessayer ci-dessous.',
    'payment.slow_body_paypal' => 'PayPal vérifie encore votre paiement. Dès qu’il est accepté, votre commande est validée automatiquement et vous recevez un e-mail. Sinon, vous pouvez réessayer ci-dessous.',
    'payment.failed_title' => 'Le paiement n’a pas abouti',
    'payment.failed_body' => 'Votre carte n’a pas été débitée. Vous pouvez réessayer, avec la même carte ou une autre, ou annuler la commande.',
    'payment.failed_body_paypal' => 'Rien ne vous a été débité. Vous pouvez réessayer avec PayPal, ou annuler la commande.',
    'payment.cancel_title' => 'Paiement interrompu',
    'payment.cancel_body' => 'Vous avez quitté la page de paiement. Votre commande est mise de côté pendant un moment : réessayez le paiement, ou annulez-la.',
    'payment.pending_title' => 'Votre commande attend son paiement',
    'payment.pending_body' => 'Finalisez le paiement par carte pour valider votre commande.',
    'payment.pending_body_paypal' => 'Finalisez le paiement avec PayPal pour valider votre commande.',
    'payment.released_title' => 'Cette commande a expiré',
    'payment.released_body' => 'Le paiement n’a pas été reçu à temps, la commande a donc été annulée. Vos articles sont toujours dans votre panier.',
    'payment.retry' => 'Réessayer le paiement par carte',
    'payment.retry_paypal' => 'Réessayer avec PayPal',
    'payment.charged_paypal' => 'Débité par PayPal : :amount',
    'payment.cancel_order' => 'Annuler la commande',
    'payment.back_to_cart' => 'Retour au panier',
    'cancel_reason.out_of_stock' => 'La pièce n’est plus disponible chez l’artisan',
    'cancel_reason.quality_issue' => 'La pièce n’a pas passé notre contrôle qualité',
    'cancel_reason.damaged' => 'La pièce a été endommagée avant l’expédition',
    'cancel_reason.buyer_request' => 'Annulée à votre demande',
    'cancel_reason.address_issue' => 'Livraison impossible à l’adresse indiquée',
    'cancel_reason.unreachable' => 'Nous n’avons pas pu vous joindre pour confirmer la commande',
    'cancel_reason.delay' => 'Le délai de fabrication serait trop long',
    'cancel_reason.duplicate' => 'Commande passée en double',
    'cancel_reason.pricing_error' => 'Erreur de prix sur la pièce',
];
