<?php

/*
 * Browser-tab titles (<Head title>) for pages that have no better key of
 * their own. The brand is appended once, by the `title` callback in app.tsx
 * ("Page — Hirfaty") — never put "Hirfaty" in a value here, or it shows
 * twice.
 *
 * Pages that already carry a translated title elsewhere (pages.privacy.title,
 * buyer.cart.title, …) keep using it; this file only fills the gaps.
 */
return [
    'home' => 'Artisanat marocain, directement des artisans',
    'about' => 'Notre histoire',
    'blog' => 'Le Journal',
    'catalogue' => 'Catalogue',

    'auth.forgot_password' => 'Mot de passe oublié',
    'auth.reset_password' => 'Réinitialiser le mot de passe',
    'auth.confirm_password' => 'Confirmer le mot de passe',
    'auth.two_factor' => 'Authentification à deux facteurs',

    'settings.profile' => 'Profil',
    'settings.security' => 'Sécurité',
    'settings.appearance' => 'Apparence',

    'seller.dashboard' => 'Tableau de bord',
    'seller.orders' => 'Commandes',
    'seller.order' => 'Commande',
    'seller.products' => 'Produits',
    'seller.sales' => 'Ventes',
    'seller.settings' => 'Paramètres',

    'admin.dashboard' => 'Administration',
    'admin.blogs' => 'Journal',
    'admin.blog_create' => 'Nouvel article',
    'admin.blog_edit' => 'Modifier l’article',
    'admin.brochure_leads' => 'Demandes de brochure',
    'admin.categories' => 'Catégories',
    'admin.cities' => 'Villes',
    'admin.delivery_rates' => 'Tarifs de livraison',
    'admin.invoices' => 'Factures',
    'admin.newsletter' => 'Abonnés à la newsletter',
    'admin.orders' => 'Achats',
    'admin.notifications' => 'Notifications',
    'admin.order' => 'Achat',
    'admin.products' => 'Produits',
    'admin.product_create' => 'Nouveau produit',
    'admin.product_edit' => 'Modifier le produit',
    'admin.stores' => 'Ateliers',
    'admin.users' => 'Utilisateurs',
    'admin.user_create' => 'Nouvel utilisateur',
    'admin.user_edit' => 'Modifier l’utilisateur',
];
