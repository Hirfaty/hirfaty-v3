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
    'expand' => 'Développer',
    'collapse' => 'Réduire',
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

    /*
     * ── La porte unique : /login ─────────────────────────────────────────
     *
     * Connexion et inscription partagent un écran, donc un espace de clés.
     * `auth.register.*` a disparu : il n'y a plus de second écran auquel il
     * pourrait appartenir. Les textes suivent l'ordre dans lequel le visiteur
     * les rencontre : identification → mot de passe (ou code) → code
     * d'inscription → informations.
     */
    'auth.login.page_title' => 'Connexion ou création de compte',
    'auth.login.back_home' => 'Retour au marché',

    // Fil d'étapes. Affiché uniquement sur la branche inscription : le
    // parcours d'un visiteur qui revient fait deux écrans, annoncer
    // « étape 1 sur 3 » serait donc faux.
    'auth.login.step_of' => 'Étape :current sur :total',
    'auth.login.step_email' => 'E-mail',
    'auth.login.step_code' => 'Code',
    'auth.login.step_details' => 'Informations',

    // ── Identification : le champ e-mail ─────────────────────────────────
    'auth.login.identify_title' => 'Connectez-vous ou',
    'auth.login.identify_title_accent' => 'créez votre compte',
    'auth.login.identify_subtitle' => 'Vous ne savez plus si vous avez déjà un compte ? Saisissez votre e-mail, nous vérifions pour vous.',
    'auth.login.email_required' => 'Adresse e-mail (obligatoire)',
    'auth.login.identify_submit' => 'Continuer',
    'auth.login.privacy_note' => 'La protection de vos informations personnelles est notre priorité. Votre e-mail ne sert qu\'à confirmer le compte.',
    'auth.login.identify_again' => 'Cela a pris trop de temps — saisissez à nouveau votre e-mail pour reprendre.',

    // Les portes artisan et professionnelle, en cartes sous le formulaire.
    // « Hirfaty Seller » et « Hirfaty Business » sont des noms de produits :
    // ils restent en alphabet latin dans toutes les langues, comme le font
    // habituellement les noms de marque.
    'auth.login.card_seller_brand' => 'Vendeur',
    'auth.login.card_seller_body' => 'Vendez votre artisanat partout au Maroc.',
    'auth.login.card_seller_cta' => 'Créer un compte artisan',
    'auth.login.card_business_body' => 'Vous achetez pour le travail ? Commandez au tarif professionnel.',
    'auth.login.card_business_cta' => 'Créer un compte professionnel',

    // ── Mot de passe : pour un compte existant ──────────────────────────
    'auth.login.password_title' => 'Bon',
    'auth.login.password_title_accent' => 'retour',
    'auth.login.password_subtitle' => 'Vous avez déjà un compte avec cette adresse. Saisissez votre mot de passe pour vous connecter.',
    'auth.login.password' => 'Mot de passe',
    'auth.login.remember' => 'Rester connecté',
    'auth.login.submit' => 'Se connecter',
    'auth.login.forgot' => 'Mot de passe oublié ?',
    'auth.login.use_another_email' => 'Utiliser une autre adresse',
    'auth.login.use_code_instead' => 'Envoyez-moi plutôt un code',
    'auth.login.use_password_instead' => 'Utiliser mon mot de passe',

    // ── Code de connexion : connexion sans mot de passe ─────────────────
    'auth.login.code_title' => 'Consultez votre',
    'auth.login.code_title_accent' => 'boîte mail',
    'auth.login.code_subtitle' => 'Nous avons envoyé un code de connexion à 6 chiffres à :email. Saisissez-le ci-dessous et vous êtes connecté — sans mot de passe.',
    'auth.login.code_submit' => 'Me connecter',
    'auth.login.code_sent' => 'Un code de connexion est en route.',
    'auth.login.code_unavailable' => 'Ce compte nécessite son mot de passe. Les codes ne sont pas disponibles pour les comptes avec vérification en deux étapes.',

    // ── Code d'inscription : confirmer une nouvelle adresse ─────────────
    'auth.login.verify_title' => 'Consultez votre',
    'auth.login.verify_title_accent' => 'boîte mail',
    'auth.login.verify_subtitle' => 'Vous êtes nouveau ici — nous avons envoyé un code à 6 chiffres à :email. Saisissez-le pour confirmer l\'adresse.',
    'auth.login.verify_submit' => 'Confirmer mon e-mail',
    'auth.login.wrong_email' => 'Mauvaise adresse ? Recommencer',
    'auth.login.session_expired' => 'Cette inscription a expiré. Saisissez votre e-mail pour recommencer.',

    // Communs aux deux écrans de code (voir CodeEntry.tsx).
    'auth.login.code_label' => 'Code de confirmation',
    'auth.login.code_invalid' => 'Ce code n\'est pas le bon. Vérifiez l\'e-mail et réessayez.',
    'auth.login.code_locked' => 'Trop de tentatives sur ce code. Demandez-en un nouveau.',
    'auth.login.code_resent' => 'Un nouveau code est en route.',
    'auth.login.code_resend' => 'Envoyer un nouveau code',
    'auth.login.code_resend_in' => 'Vous pourrez demander un nouveau code dans :seconds s',
    'auth.login.resend_wait' => 'Patientez encore :seconds secondes avant de demander un autre code.',
    'auth.login.code_spam_hint' => 'Toujours rien ? Cela peut prendre une minute — pensez aussi au dossier spam.',

    // ── Reprendre une inscription inachevée ─────────────────────────────
    'auth.login.resume_title' => 'Vous avez une inscription en cours',
    'auth.login.resume_body' => 'Nous avons déjà envoyé un code à :email.',
    'auth.login.resume_cta' => 'Reprendre où j\'en étais',

    // ── Page d'attente Hirfaty Business ─────────────────────────────────
    'auth.login.placeholder_soon' => 'Bientôt disponible',
    'auth.login.placeholder_business_title' => 'Les comptes professionnels s\'ouvrent à la main.',
    'auth.login.placeholder_business_body' => 'Les tarifs de gros sont validés avant d\'être accordés : les comptes professionnels ne sont donc pas encore en libre-service. Contactez-nous et nous créerons le vôtre. Vous achetez déjà chez nous ? Vous pouvez demander l\'accès professionnel depuis votre compte.',
    'auth.login.placeholder_contact' => 'Nous contacter',
    'auth.login.placeholder_back' => 'Je suis client — retour à la connexion',

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

    // ── Champs des informations (étape 3 de l'inscription) ──────────────
    'auth.login.first_name' => 'Prénom',
    'auth.login.last_name' => 'Nom',
    'auth.login.phone' => 'Téléphone',
    'auth.login.password_confirm' => 'Confirmer le mot de passe',
    'auth.login.password_hint' => 'Au moins 12 caractères, avec majuscules, minuscules, un chiffre et un symbole.',
    'auth.login.details_title' => 'Presque',
    'auth.login.details_title_accent' => 'terminé',
    'auth.login.details_subtitle' => 'Juste un mot de passe. Vous compléterez votre profil plus tard, depuis votre compte.',
    'auth.login.details_submit' => 'Créer mon compte',
    'auth.login.email_confirmed' => 'E-mail confirmé',

    // ── Jauge de robustesse — un libellé par barre remplie ──────────────
    'auth.login.password_strength_very_weak' => 'Très faible',
    'auth.login.password_strength_weak' => 'Faible',
    'auth.login.password_strength_fair' => 'Moyenne',
    'auth.login.password_strength_good' => 'Bonne',
    'auth.login.password_strength_strong' => 'Excellente',

    'auth.login.terms_prefix' => 'J\'accepte la',
    'auth.login.terms_privacy' => 'politique de confidentialité',
    'auth.login.terms_and' => 'et les',
    'auth.login.terms_conditions' => 'conditions d\'utilisation',

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
    // ── Colonnes du pied de page (Explorer / À propos / Service client) ──
    'footer.explore.title' => 'Explorer',
    'footer.explore.affiliate' => 'Programme d’affiliation',
    'footer.explore.blog' => 'Journal',
    'footer.explore.rewards' => 'Récompenses',

    'footer.about.title' => 'À propos',
    'footer.about.about' => 'Qui sommes-nous',

    'footer.care.title' => 'Service client',
    'footer.care.privacy' => 'Politique de confidentialité',
    'footer.care.terms' => 'Conditions d’utilisation',
    'footer.care.shipping' => 'Politique de livraison',
    'footer.care.bulk' => 'Acheter en gros',

    'footer.follow.title' => 'Suivre',

    // `:year` est remplacé côté React par l'année en cours.
    'footer.copyright' => '© :year Hirfaty.com — Tous droits réservés.',
    'footer.shipping' => 'Livraison dans tout le Maroc.',
    // ── Connexion via Google / Facebook ─────────────────────────────────
    'auth.login.social_divider' => 'ou',
    'auth.login.social_google' => 'Continuer avec Google',
    'auth.login.social_facebook' => 'Continuer avec Facebook',
    'auth.login.social_failed' => 'La connexion a échoué. Veuillez réessayer.',
    'auth.login.social_unusable' => 'Ce compte ne nous a pas transmis d’adresse e-mail confirmée. Utilisez votre e-mail ci-dessus.',
    'auth.login.social_banned' => 'Ce compte est suspendu. Contactez-nous si vous pensez qu’il s’agit d’une erreur.',

    // ── Devise ───────────────────────────────────────────────────────────
    // Les prix sont enregistrés et facturés en MAD ; la devise affichée suit
    // l'IP du visiteur, sauf s'il en choisit une depuis l'en-tête.
    'currency_label' => 'Devise affichée',
    'language_label' => 'Langue',
    'region_label' => 'Langue et devise',
    'currency_note' => 'Prix affichés en :currency à titre indicatif. Les commandes sont facturées en dirhams (MAD).',
    'currency_charged' => 'Facturé : :amount',
];
