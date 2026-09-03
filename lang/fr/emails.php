<?php

/*
 * Email copy.
 *
 * Nested arrays are fine here, unlike lang/<locale>/{common,buyer,seller,...}:
 * those are shipped to the browser and read by useTranslation(), which splits a
 * key on its first dot only and so cannot see into a nested array. This file is
 * never in that list — it is resolved server-side with __() while rendering a
 * mail view, so it can be structured properly.
 */

return [
    'verify' => [
        // Shared chrome
        'cta' => 'Vérifier mon adresse email',
        'expires' => 'Ce lien expire dans :minutes minutes.',
        'fallback' => 'Le bouton ne fonctionne pas ? Copiez ce lien dans votre navigateur :',
        'ignore' => 'Vous n’avez pas créé de compte sur Hirfaty ? Ignorez cet email, aucun compte ne sera activé.',
        'tagline' => 'La plateforme des artisans marocains.',
        'privacy' => 'Confidentialité',
        'terms' => 'Conditions d’utilisation',
        'greeting' => 'Bonjour :name,',

        'buyer' => [
            'subject' => 'Vérifiez votre adresse email — Hirfaty',
            'preheader' => 'Une dernière étape pour activer votre compte Hirfaty.',
            'eyebrow' => 'Compte acheteur',
            'headline' => 'bienvenue chez Hirfaty.',
            'body' => 'Merci de votre inscription. Vérifiez votre adresse email pour découvrir des pièces faites main par des artisans partout au Maroc.',
            'next_title' => 'Ce qui vous attend',
            'steps' => [
                [
                    'title' => 'Explorez le marché',
                    'body' => 'Poterie, maroquinerie, textiles, bijoux et bien plus encore.',
                ],
                [
                    'title' => 'Commandez en confiance',
                    'body' => 'Livraison Amana sécurisée partout au Maroc.',
                ],
                [
                    'title' => 'Suivez vos commandes',
                    'body' => 'Un numéro de suivi vous est envoyé dès l’expédition.',
                ],
            ],
        ],

        'seller' => [
            'subject' => 'Activez votre atelier — Hirfaty',
            'preheader' => 'Une dernière étape pour ouvrir votre boutique Hirfaty.',
            'eyebrow' => 'Compte vendeur',
            'headline' => 'activez votre atelier.',
            'body' => 'Vous êtes à un clic de rejoindre la communauté des artisans. Vérifiez votre adresse email pour commencer à configurer votre boutique.',
            'next_title' => 'Ce qui vous attend ensuite',
            'steps' => [
                [
                    'title' => 'Configurez votre boutique',
                    'body' => 'Nom, catégorie, ville et description de votre atelier.',
                ],
                [
                    'title' => 'Ajoutez vos pièces',
                    'body' => 'Photos, prix et description. Chaque pièce est relue avant publication.',
                ],
                [
                    'title' => 'Commencez à vendre',
                    'body' => 'Recevez des commandes, expédiez via Amana et suivez vos revenus.',
                ],
            ],
        ],
    ],
];
