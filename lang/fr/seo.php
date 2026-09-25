<?php

/*
 * Search-engine and link-preview copy: <meta name="description">, Open Graph
 * and the few labels SeoService puts into structured data.
 *
 * Server-only on purpose — SeoService renders these into the first HTML
 * response, where crawlers and WhatsApp/Facebook previews read them. That is
 * why this file is not in HandleInertiaRequests::getTranslations(): the React
 * pages never need it.
 *
 * Descriptions stay under ~155 characters: Google truncates longer ones, and
 * the tail is where a cut would land. `:placeholders` are filled per page.
 */
return [
    'default' => 'Hirfaty, la place de marché de l’artisanat marocain : poterie, zellige, cuir, textile et bois, achetés directement aux artisans.',

    'home' => 'Achetez l’artisanat marocain directement aux artisans : poterie, zellige, cuir, textile, bois. Pièces uniques livrées au Maroc et à l’étranger.',
    'catalogue' => 'Toutes les pièces faites main par les artisans marocains de Hirfaty : décoration, art de vivre, bijoux, cuir et textile.',
    'catalogue_category' => ':category faits main par des artisans marocains, à acheter directement sur Hirfaty. Pièces uniques, livrées au Maroc et à l’étranger.',
    'new' => 'Les dernières pièces mises en ligne par les artisans de Hirfaty : nouveautés en poterie, cuir, textile, bois et décoration marocaine.',
    'blog' => 'Le Journal de Hirfaty : portraits d’artisans, savoir-faire marocains et histoires derrière les pièces faites main.',
    'about' => 'L’histoire de Hirfaty : une place de marché qui relie directement les artisans marocains à leurs acheteurs, sans intermédiaire.',
    'how' => 'Comment fonctionne Hirfaty : explorez les ateliers, commandez directement aux artisans, recevez votre pièce livrée chez vous.',
    'contact' => 'Une question sur une commande ou envie d’ouvrir votre atelier sur Hirfaty ? Écrivez-nous, nous répondons sous deux jours ouvrés.',
    'affiliate' => 'Le programme d’affiliation Hirfaty : partagez l’artisanat marocain avec votre audience et gagnez une commission sur chaque vente.',
    'rewards' => 'Le programme de récompenses Hirfaty : cumulez des points à chaque commande d’artisanat marocain et dépensez-les sur la suivante.',
    'privacy' => 'Politique de confidentialité de Hirfaty : ce que nous collectons, pourquoi, qui y a accès et comment exercer vos droits.',
    'shipping' => 'Politique de livraison de Hirfaty : délais, tarifs et zones de livraison au Maroc et à l’international.',
    'terms' => 'Conditions d’utilisation de Hirfaty pour les acheteurs et les artisans de la place de marché.',
    'business' => 'Hirfaty pour les professionnels : achetez l’artisanat marocain en volume, aux tarifs réservés aux comptes pro.',
    'sell' => 'Vous êtes artisan marocain ? Ouvrez votre atelier sur Hirfaty et vendez vos créations au Maroc et à l’international.',
    'professional' => 'Comptes professionnels Hirfaty : tarifs dédiés et commandes en volume pour hôtels, décorateurs et boutiques.',

    'product' => ':product, pièce faite main par :store. Achetez-la directement à l’artisan sur Hirfaty, livrée au Maroc et à l’étranger.',
    'store' => ':store, atelier d’artisanat marocain. Découvrez ses pièces faites main et achetez directement à l’artisan sur Hirfaty.',
    'store_city' => ':store, atelier d’artisanat à :city. Découvrez ses pièces faites main et achetez directement à l’artisan sur Hirfaty.',

    'breadcrumb.home' => 'Accueil',
];
