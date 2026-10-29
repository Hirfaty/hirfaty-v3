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
                    'body' => 'Livraison Cathedis sécurisée partout au Maroc.',
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
                    'body' => 'Recevez des commandes, expédiez via Cathedis et suivez vos revenus.',
                ],
            ],
        ],
    ],

    /*
     * Le code d'inscription à 6 chiffres. Pas de salutation nominative ici,
     * contrairement à verify.* : cet e-mail part avant que le compte existe,
     * il n'y a donc aucun prénom à afficher.
     */
    'registration_code' => [
        'subject' => 'Votre code Hirfaty : :code',
        'preheader' => ':code est votre code de confirmation Hirfaty.',
        'eyebrow' => 'Créer un compte',
        'headline' => 'Voici votre code.',
        'body' => 'Cliquez sur le bouton pour confirmer votre adresse e-mail et terminer la création de votre compte — ou saisissez le code ci-dessous sur la page Hirfaty que vous venez de quitter.',
        'button' => 'Confirmer mon e-mail',
        'expires' => 'Ce code expire dans :minutes minutes.',
        'ignore' => 'Vous n’avez pas demandé à rejoindre Hirfaty ? Ignorez cet e-mail — aucun compte ne sera créé et le code cessera de fonctionner de lui-même.',
        'tagline' => 'La maison des artisans marocains.',
        'privacy' => 'Confidentialité',
        'terms' => 'Conditions d’utilisation',
    ],

    /*
     * Le code de connexion à 6 chiffres, pour quelqu'un qui a déjà un compte
     * mais a choisi un code plutôt que son mot de passe. Même gabarit que
     * registration_code (voir emails/auth-code.blade.php) : seuls les mots
     * changent, le jeu de clés doit donc rester identique au bloc ci-dessus.
     *
     * La ligne `ignore` est formulée comme un avertissement et non comme une
     * banalité : un code de connexion inattendu signifie que quelqu'un a saisi
     * cette adresse sur l'écran de connexion, ce qui mérite d'être remarqué.
     */
    'login_code' => [
        'subject' => 'Votre code de connexion Hirfaty : :code',
        'preheader' => ':code est votre code de connexion Hirfaty.',
        'eyebrow' => 'Connexion',
        'headline' => 'Voici votre code de connexion.',
        'body' => 'Saisissez ce code sur la page de connexion Hirfaty pour accéder à votre compte. Votre mot de passe ne sera pas nécessaire.',
        'expires' => 'Ce code expire dans :minutes minutes.',
        'ignore' => 'Vous n’avez pas tenté de vous connecter ? Vous pouvez ignorer cet e-mail — le code expire de lui-même et rien n’a changé sur votre compte. Si cela se répète, changez votre mot de passe.',
        'tagline' => 'La maison des artisans marocains.',
        'privacy' => 'Confidentialité',
        'terms' => 'Conditions d’utilisation',
    ],
];
