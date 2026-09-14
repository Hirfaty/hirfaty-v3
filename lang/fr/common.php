<?php

return [
    'app_name' => 'Hirfaty',
    'save' => 'Enregistrer',
    'cancel' => 'Annuler',
    'delete' => 'Supprimer',
    'edit' => 'Modifier',
    'view' => 'Voir',
    'search' => 'Rechercher',
    'filter' => 'Filtrer',
    'loading' => 'Chargement...',
    'confirm' => 'Êtes-vous sûr ?',
    'back' => 'Retour',
    'next' => 'Suivant',
    'submit' => 'Envoyer',
    'total' => 'Total',
    'status' => 'Statut',
    'date' => 'Date',
    'actions' => 'Actions',
    'yes' => 'Oui',
    'no' => 'Non',
    'welcome' => 'Bienvenue',
    'logout' => 'Déconnexion',
    'login' => 'Connexion',
    'register' => 'S\'inscrire',

    // ── Écran de connexion ───────────────────────────────────────────────
    'auth.login.eyebrow' => 'Bon retour',
    'auth.login.title' => 'De retour parmi',
    'auth.login.title_accent' => 'les artisans',
    'auth.login.subtitle' => 'Connectez-vous pour retrouver vos commandes, vos pièces sauvegardées et vos ateliers favoris.',
    'auth.login.email' => 'Adresse e-mail',
    'auth.login.email_placeholder' => 'vous@exemple.com',
    'auth.login.password' => 'Mot de passe',
    'auth.login.password_placeholder' => 'Votre mot de passe',
    'auth.login.remember' => 'Rester connecté',
    'auth.login.forgot' => 'Mot de passe oublié ?',
    'auth.login.submit' => 'Se connecter',
    'auth.login.no_account' => 'Nouveau sur Hirfaty ?',
    'auth.login.create_account' => 'Créer un compte',
    'auth.login.back_home' => 'Retour au marché',
    'auth.login.quote' => 'Mille mains, un seul souk. Reprenez là où vous vous êtes arrêté.',
    'auth.login.trust_secure' => 'Connexion sécurisée',
    'auth.login.trust_artisans' => 'Ateliers d\'artisans',
    'auth.login.trust_cities' => 'Villes marocaines',

    // ── Écran d'inscription ──────────────────────────────────────────────
    'auth.register.eyebrow' => 'Créer un compte',
    'auth.register.buyer.title' => 'Découvrez l\'artisanat',
    'auth.register.buyer.title_accent' => 'qui vous ressemble',
    'auth.register.buyer.subtitle' => 'Suivez vos ateliers préférés, sauvegardez des pièces et commandez en quelques clics.',
    'auth.register.buyer.submit' => 'Créer mon compte',
    'auth.register.buyer.quote' => 'Chaque pièce a une main, une ville, une histoire. Notre rôle est simplement de vous mettre en relation.',
    'auth.register.seller.title' => 'Ouvrez votre',
    'auth.register.seller.title_accent' => 'atelier en ligne',
    'auth.register.seller.subtitle' => 'Présentez votre savoir-faire à une audience qui cherche l\'authentique.',
    'auth.register.seller.submit' => 'Continuer vers ma boutique',
    'auth.register.seller.quote' => 'Hirfaty appartient à ses artisans. Aucun frais d\'ouverture, votre boutique reste la vôtre.',

    'auth.register.role_question' => 'Je m\'inscris en tant que',
    'auth.register.role_buyer' => 'Client',
    'auth.register.role_buyer_hint' => 'Pour découvrir et acheter',
    'auth.register.role_seller' => 'Artisan',
    'auth.register.role_seller_hint' => 'Pour ouvrir un atelier',

    // ── Écran de vérification d'e-mail ───────────────────────────────────
    'auth.verify.eyebrow' => 'Sécurité du compte',
    'auth.verify.title' => 'Vérifiez votre',
    'auth.verify.title_accent' => 'e-mail',
    'auth.verify.subtitle' => 'Merci de nous avoir rejoints ! Cliquez sur le lien que nous venons d\'envoyer à',
    'auth.verify.sent_banner' => 'Un nouveau lien de vérification a été envoyé à l\'adresse e-mail que vous avez fournie lors de l\'inscription.',
    'auth.verify.resend' => 'Renvoyer l\'e-mail de vérification',
    'auth.verify.spam_hint' => 'Pas encore reçu ? Pensez à vérifier vos spams.',
    'auth.verify.logout' => 'Se déconnecter',
    'auth.verify.quote' => 'Un compte vérifié, c\'est la confiance du souk préservée — pour les artisans comme pour les acheteurs.',

    'auth.register.first_name' => 'Prénom',
    'auth.register.last_name' => 'Nom',
    'auth.register.phone' => 'Téléphone',
    'auth.register.email' => 'Adresse e-mail',
    'auth.register.password' => 'Mot de passe',
    'auth.register.password_confirm' => 'Confirmer le mot de passe',
    'auth.register.password_hint' => 'Au moins 12 caractères, avec majuscules, minuscules, un chiffre et un symbole.',

    // ── Jauge de robustesse — un libellé par barre remplie ──────────
    'auth.register.password_strength_very_weak' => 'Très faible',
    'auth.register.password_strength_weak' => 'Faible',
    'auth.register.password_strength_fair' => 'Moyenne',
    'auth.register.password_strength_good' => 'Bonne',
    'auth.register.password_strength_strong' => 'Excellente',

    'auth.register.terms_prefix' => 'J\'accepte la',
    'auth.register.terms_privacy' => 'politique de confidentialité',
    'auth.register.terms_and' => 'et les',
    'auth.register.terms_conditions' => 'conditions d\'utilisation',

    'auth.register.have_account' => 'Vous avez déjà un compte ?',
    'auth.register.sign_in' => 'Se connecter',

    // ── Pages d'erreur ───────────────────────────────────────────────────
    // Rendues par Inertia via le gestionnaire dans bootstrap/app.php.
    'errors.eyebrow' => 'Erreur :status',

    'errors.404.title' => 'Cette page a quitté l’atelier',
    'errors.404.body' => 'Le lien que vous avez suivi ne mène nulle part. La pièce a peut-être été retirée, ou l’adresse comporte une faute.',

    'errors.403.title' => 'Cette porte reste fermée',
    'errors.403.body' => 'Vous n’avez pas accès à cette page avec ce compte.',

    'errors.500.title' => 'Quelque chose s’est brisé',
    'errors.500.body' => 'Une erreur est survenue de notre côté. Nous en sommes informés — réessayez dans un instant.',

    'errors.503.title' => 'Nous revenons très vite',
    'errors.503.body' => 'Le site est en maintenance pour quelques minutes. Merci de votre patience.',

    'errors.back_home' => 'Retour à l’accueil',
    'errors.browse_market' => 'Explorer le marché',

    /*
     * La signature arabe du pied de page, sous le paragraphe d'accroche.
     * Conservée identique dans les trois fichiers de langue plutôt que
     * traduite en français/anglais — même logique que
     * `welcome.hero.badge_script` et `welcome.seller_cta.ar_line` : une
     * fioriture ornementale dans la langue d'origine de la marque, pas un
     * texte qui change avec la langue active.
     */
    'footer.ar_line' => 'حرفتي — صنع بفخر في المغرب',

    'footer.newsletter.title' => 'Les lettres du souk',
    'footer.newsletter.blurb' => 'Les nouvelles pièces, les nouveaux ateliers et les histoires qui vont avec. Une fois par mois, jamais plus.',
    'footer.newsletter.label' => 'Votre adresse e-mail',
    'footer.newsletter.placeholder' => 'vous@exemple.com',
    'footer.newsletter.submit' => 'Je m’inscris',
    'footer.newsletter.success' => 'Vous êtes inscrit. Guettez la prochaine lettre du souk.',
    'footer.newsletter.invalid' => 'Saisissez une adresse e-mail valide.',

    // ── Le reste du pied de page ─────────────────────────────────────────
    // Tout ceci était écrit en dur en français dans `SiteFooter.tsx`, et
    // restait donc en français sur les versions arabe et anglaise du site.
    'footer.blurb' => 'La place de marché de l’artisanat marocain. Des objets faits à la main, par des artisans de Fès, Marrakech, Chefchaouen et au-delà.',

    'footer.buyers.title' => 'Acheteurs',
    'footer.buyers.explore' => 'Explorer',
    'footer.buyers.how' => 'Comment ça marche',

    'footer.artisans.title' => 'Artisans',
    'footer.artisans.sell' => 'Vendre sur Hirfaty',

    'footer.company.title' => 'Société',
    'footer.company.contact' => 'Contact',

    'footer.follow.title' => 'Suivre',

    // `:year` est remplacé côté React par l'année en cours.
    'footer.copyright' => '© :year Hirfaty.com — Tous droits réservés.',
    'footer.shipping' => 'Livraison dans tout le Maroc.',
];
