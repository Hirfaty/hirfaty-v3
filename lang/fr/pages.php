<?php

/*
 * The two standalone pages the footer links to: "Comment ça marche" and
 * "Contact".
 *
 * Contact *details* are not repeated here — the page reads them from
 * `welcome.contact.*`, which the homepage's contact section already uses, so
 * there is one place to change the address and phone number rather than two
 * that can drift apart.
 */
return [
    // ── Comment ça marche ────────────────────────────────────────────────
    'how.title' => 'Comment ça marche',
    'how.eyebrow' => 'Hirfaty en bref',
    'how.heading' => 'De l’atelier à votre porte',
    'how.lead' => 'Hirfaty est la place de marché de l’artisanat marocain : les artisans y vendent ce qu’ils fabriquent eux-mêmes, directement, sans intermédiaire entre leur établi et votre maison.',

    'how.step1.title' => 'Explorez le souk',
    'how.step1.body' => 'Chaque atelier a sa propre boutique. Parcourez par catégorie ou par ville, et arrêtez-vous où votre regard s’arrête — comme dans une vraie médina.',
    'how.step2.title' => 'Commandez directement',
    'how.step2.body' => 'Votre commande part chez l’artisan qui a façonné la pièce. Il la prépare lui-même, et l’essentiel du prix lui revient.',
    'how.step3.title' => 'Amana livre',
    'how.step3.body' => 'Livraison suivie jusqu’à votre porte, partout au Maroc. Vous suivez la commande depuis votre compte, du premier jour à la réception.',

    'how.sellers.heading' => 'Vous êtes artisan ?',
    'how.sellers.body' => 'Ouvrez votre boutique sur Hirfaty, publiez vos pièces et vendez à la fois aux particuliers et aux professionnels — depuis un seul tableau de bord.',
    'how.sellers.cta' => 'Vendre sur Hirfaty',

    'how.buyers.cta' => 'Explorer les produits',

    // ── Contact ──────────────────────────────────────────────────────────
    'contact.title' => 'Contact',
    'contact.eyebrow' => 'Nous écrire',
    'contact.heading' => 'Parlons-en',
    'contact.lead' => 'Une question sur une commande, une boutique à ouvrir, ou simplement envie d’en savoir plus ? Écrivez-nous — nous répondons sous deux jours ouvrés.',

    'contact.hours_label' => 'Horaires',
    'contact.hours' => 'Du lundi au vendredi, 9h – 18h (GMT+1)',

    'contact.seller.heading' => 'Vous voulez vendre sur Hirfaty ?',
    'contact.seller.body' => 'Créez votre compte artisan : la mise en ligne de votre boutique se fait en quelques minutes, et notre équipe la vérifie avant publication.',
    'contact.seller.cta' => 'Ouvrir ma boutique',

    // ── Programme d'affiliation ──────────────────────────────────────────
    // Aucun taux de commission n'est annoncé ici : les conditions sont
    // négociées partenaire par partenaire pour l'instant, et un chiffre
    // imprimé sur cette page serait le seul que personne ne penserait à
    // mettre à jour.
    'affiliate.title' => 'Programme d’affiliation',
    'affiliate.eyebrow' => 'Devenez partenaire',
    'affiliate.heading' => 'Partagez l’artisanat marocain. Gagnez sur chaque commande.',
    'affiliate.lead' => 'Si vous écrivez, filmez ou publiez sur le design, le voyage ou le Maroc, notre programme d’affiliation transforme votre audience en revenu — et envoie du travail aux ateliers derrière chaque pièce.',

    'affiliate.how.heading' => 'Comment ça marche',
    'affiliate.how.step1.title' => 'Candidatez',
    'affiliate.how.step1.body' => 'Envoyez-nous votre profil et vos espaces de publication. Nous répondons sous cinq jours ouvrés environ.',
    'affiliate.how.step2.title' => 'Partagez votre lien',
    'affiliate.how.step2.body' => 'Vous recevez un lien personnel et un jeu de photographies libres d’usage. Publiez là où se trouve votre audience.',
    'affiliate.how.step3.title' => 'Gagnez sur chaque vente',
    'affiliate.how.step3.body' => 'Toute commande passée via votre lien vous rapporte une commission, versée mensuellement par virement.',

    'affiliate.who.heading' => 'Qui nous cherchons',
    'affiliate.who.body' => 'Des créateurs dont l’audience est réellement à l’écoute — la taille compte moins que cela. Architectes d’intérieur, plumes du voyage, comptes cuisine et artisanat, et tous ceux dont les lecteurs se soucient de la provenance des objets.',
    'affiliate.who.point1' => 'Un compte actif sur une grande plateforme, alimenté ces deux dernières semaines.',
    'affiliate.who.point2' => 'Un contenu qui s’accorde naturellement avec l’artisanat marocain.',
    'affiliate.who.point3' => 'Partout dans le monde — nous livrons bien au-delà du Maroc.',

    'affiliate.terms.heading' => 'Commission et paiement',
    'affiliate.terms.body' => 'Les taux se négocient avec chaque partenaire plutôt que d’être fixes : un compte cuisine et un studio de décoration n’ont pas à entrer dans le même moule. Votre taux, votre fenêtre d’attribution et votre calendrier de paiement vous sont confirmés par écrit avant de commencer.',

    'affiliate.cta.heading' => 'Postuler',
    'affiliate.cta.body' => 'Écrivez-nous avec votre nom, vos espaces de publication et un lien ou deux. C’est toute la candidature.',
    'affiliate.cta.button' => 'Nous écrire',

    // ── Récompenses ──────────────────────────────────────────────────────
    // Le programme de points n'est pas encore construit : rien ici ne lit
    // un solde, et la dernière section le dit clairement plutôt que de
    // laisser croire qu'un compte a déjà des points.
    'rewards.title' => 'Récompenses',
    'rewards.eyebrow' => 'Récompenses Hirfaty',
    'rewards.heading' => 'Chaque commande vous rapproche de la suivante.',
    'rewards.lead' => 'Cumulez des points à chaque achat, puis dépensez-les sur le suivant. Gratuit, et rien à retenir.',

    'rewards.earn.heading' => 'Cumuler des points',
    'rewards.earn.shop.title' => 'Commander',
    'rewards.earn.shop.body' => '1 point pour chaque dirham dépensé.',
    'rewards.earn.signup.title' => 'Créer un compte',
    'rewards.earn.signup.body' => '200 points, une seule fois.',
    'rewards.earn.review.title' => 'Laisser un avis',
    'rewards.earn.review.body' => '100 points par pièce reçue.',
    'rewards.earn.birthday.title' => 'Votre anniversaire',
    'rewards.earn.birthday.body' => '250 points, chaque année.',

    'rewards.spend.heading' => 'Les dépenser',
    'rewards.spend.body' => 'Les points se déduisent du panier au moment de payer. 100 points valent 10 MAD — une commande de 500 MAD vous rapporte donc 50 MAD pour la prochaine.',
    'rewards.spend.note' => 'Les points n’expirent pas tant que votre compte est actif, et s’appliquent au panier entier, pas à un seul atelier.',

    'rewards.tiers.heading' => 'Niveaux',
    'rewards.tiers.body' => 'Plus vous commandez dans l’année, plus vite vous cumulez.',
    'rewards.tiers.silver' => 'Argent',
    'rewards.tiers.silver_body' => 'Dès la première commande. 1 point par dirham.',
    'rewards.tiers.gold' => 'Or',
    'rewards.tiers.gold_body' => 'À partir de 3 000 MAD par an. 1,25 point par dirham, et l’accès en avant-première aux nouvelles pièces.',
    'rewards.tiers.platinum' => 'Platine',
    'rewards.tiers.platinum_body' => 'À partir de 8 000 MAD par an. 1,5 point par dirham, et la livraison offerte au Maroc.',

    'rewards.soon.heading' => 'Bientôt disponible',
    'rewards.soon.body' => 'Le programme de récompenses est en cours de construction. Créez votre compte dès maintenant : vos commandes compteront dès son ouverture.',
    'rewards.soon.button' => 'Parcourir le marché',

    // ── Politique de confidentialité ─────────────────────────────────────
    'privacy.title' => 'Politique de confidentialité',
    'privacy.eyebrow' => 'Vos données',
    'privacy.heading' => 'Ce que nous collectons, et ce que nous en faisons.',
    'privacy.lead' => 'En résumé : nous collectons ce qu’une commande exige, et rien que nous ne puissions justifier. Nous ne vendons pas vos données.',
    'privacy.updated' => 'Dernière mise à jour : septembre 2026',

    'privacy.collect.heading' => 'Ce que nous collectons',
    'privacy.collect.body1' => 'À la création du compte : nom, adresse e-mail et numéro de téléphone. À la commande : l’adresse de livraison et le numéro que le livreur doit appeler.',
    'privacy.collect.body2' => 'Nous lisons aussi le pays d’où semble venir votre connexion, uniquement pour choisir la devise à afficher. Vous pouvez la changer depuis l’en-tête à tout moment.',

    'privacy.use.heading' => 'À quoi cela sert',
    'privacy.use.body' => 'Traiter et livrer votre commande, vous répondre quand vous nous écrivez, et envoyer les e-mails liés à une commande — confirmations, avis d’expédition et assimilés. Les e-mails marketing ne partent que si vous les avez demandés, et chacun porte un moyen de se désinscrire.',

    'privacy.share.heading' => 'Qui y a accès',
    'privacy.share.body' => 'L’atelier qui prépare votre commande voit ce dont il a besoin pour le faire. Notre partenaire de livraison voit l’adresse et le téléphone pour pouvoir livrer. Personne d’autre — nous ne vendons ni ne louons vos coordonnées.',

    'privacy.rights.heading' => 'Vos droits',
    'privacy.rights.body' => 'Vous pouvez demander une copie de ce que nous détenons, demander une correction, ou demander la fermeture de votre compte. Écrivez-nous et nous répondrons. Certains enregistrements de commande doivent être conservés pour des raisons comptables, même après la fermeture d’un compte.',

    'privacy.contact.heading' => 'Nous contacter',
    'privacy.contact.body' => 'Toute question sur ce qui précède, ou toute demande concernant vos données, passe par la même adresse :',

    // ── Politique de livraison ───────────────────────────────────────────
    // Les chiffres sont les vrais : DeliveryRateSeeder facture 40 MAD
    // forfaitaires au Maroc et CheckoutRequest n'accepte que le paiement à
    // la livraison.
    'shipping.title' => 'Politique de livraison',
    'shipping.eyebrow' => 'Livraison',
    'shipping.heading' => 'Comment votre commande vous parvient.',
    'shipping.lead' => 'Hirfaty récupère votre commande auprès des ateliers qui l’ont faite et l’expédie en un seul colis.',

    'shipping.morocco.heading' => 'Au Maroc',
    'shipping.morocco.body' => '40 MAD forfaitaires par commande, où que vous soyez dans le pays — un seul frais de livraison pour tout le panier, même s’il vient de plusieurs ateliers. La livraison prend en général 3 à 7 jours.',

    'shipping.abroad.heading' => 'À l’international',
    'shipping.abroad.body' => 'Nous livrons en Europe, en Amérique du Nord et dans le Golfe. Le montant dépend de la destination et s’affiche au moment de payer, avant confirmation — rien n’est ajouté ensuite.',

    'shipping.payment.heading' => 'Le paiement',
    'shipping.payment.body' => 'Les commandes sont payées à la livraison : vous réglez le livreur à la réception, en dirhams. Si le site vous affiche des prix dans une autre devise, c’est à titre indicatif — le montant encaissé est le total en dirhams affiché au moment de payer.',

    'shipping.tracking.heading' => 'Suivre votre commande',
    'shipping.tracking.body' => 'Vous recevez un e-mail quand l’atelier confirme votre commande, puis un autre au départ en livraison, avec un numéro de suivi. Tout se retrouve aussi dans votre espace commandes.',

    'shipping.handmade.heading' => 'Une note sur le fait main',
    'shipping.handmade.body' => 'Chaque pièce est faite à la main, et certaines ne sont façonnées qu’une fois commandées. Si une pièce demande plus de temps que d’habitude à l’atelier, nous vous le disons plutôt que de laisser la date glisser en silence.',
];
