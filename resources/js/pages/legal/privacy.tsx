import { Head } from '@inertiajs/react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/site/SiteHeader';

const sections = [
    {
        title: '1. Objet',
        body: (
            <p>
                La présente Politique de confidentialité décrit comment Hirfaty
                collecte, utilise, partage et protège les données à caractère
                personnel des utilisateurs de sa Plateforme (Acheteurs, Vendeurs
                et Administrateurs), conformément à la loi marocaine n°09-08
                relative à la protection des personnes physiques à l'égard du
                traitement des données à caractère personnel.
            </p>
        ),
    },
    {
        title: '2. Données collectées',
        body: (
            <>
                <p>
                    <strong>Données de compte</strong> — nom, prénom, adresse
                    e-mail, numéro de téléphone, mot de passe (chiffré), rôle
                    (acheteur, vendeur, administrateur) et, pour les vendeurs,
                    les informations relatives à leur boutique (nom, catégorie,
                    ville, description).
                </p>
                <p>
                    <strong>Données de commande</strong> — adresse de livraison,
                    historique des commandes, produits consultés ou ajoutés au
                    panier ou à la liste de souhaits.
                </p>
                <p>
                    <strong>Données techniques</strong> — adresse IP,
                    identifiants de session, langue et préférences d'affichage,
                    journaux de connexion, à des fins de sécurité et
                    d'amélioration du service.
                </p>
                <p>
                    Hirfaty ne stocke jamais les données bancaires complètes :
                    les paiements sont traités par des prestataires de paiement
                    tiers soumis à leurs propres obligations de sécurité.
                </p>
            </>
        ),
    },
    {
        title: '3. Finalités du traitement',
        body: (
            <p>
                Vos données sont utilisées pour : créer et gérer votre compte ;
                traiter vos commandes et paiements ; assurer la livraison via
                notre partenaire logistique Amana ; assurer la modération des
                produits et la sécurité de la Plateforme ; vous envoyer des
                notifications relatives à vos commandes ou à votre compte ; et,
                sous réserve de votre consentement, améliorer nos services et
                vous informer de nos actualités.
            </p>
        ),
    },
    {
        title: '4. Partage des données',
        body: (
            <p>
                Vos données peuvent être partagées avec : le Vendeur concerné
                par une commande (informations de livraison) ; notre partenaire
                de livraison Amana ; nos prestataires de paiement ; et, le cas
                échéant, les autorités compétentes lorsque la loi l'exige.
                Hirfaty ne vend ni ne loue vos données personnelles à des tiers
                à des fins commerciales.
            </p>
        ),
    },
    {
        title: '5. Durée de conservation',
        body: (
            <p>
                Vos données sont conservées pendant la durée nécessaire aux
                finalités décrites ci-dessus, et notamment pendant toute la
                durée de vie de votre compte. Certaines données (factures,
                commandes) peuvent être conservées au-delà pour répondre à nos
                obligations légales, comptables ou fiscales.
            </p>
        ),
    },
    {
        title: '6. Sécurité',
        body: (
            <p>
                Hirfaty met en œuvre des mesures techniques et
                organisationnelles raisonnables (chiffrement des mots de passe,
                contrôle d'accès par rôle, connexions sécurisées) afin de
                protéger vos données contre tout accès, altération, divulgation
                ou destruction non autorisés.
            </p>
        ),
    },
    {
        title: '7. Vos droits',
        body: (
            <p>
                Conformément à la loi n°09-08 et aux textes applicables, vous
                disposez d'un droit d'accès, de rectification, d'opposition et
                de suppression de vos données personnelles. Vous pouvez exercer
                ces droits directement depuis les paramètres de votre compte ou
                en nous contactant à l'adresse indiquée ci-dessous. Vous
                disposez également du droit de saisir la Commission Nationale de
                contrôle de la protection des Données à caractère Personnel
                (CNDP).
            </p>
        ),
    },
    {
        title: '8. Cookies',
        body: (
            <p>
                La Plateforme utilise des cookies strictement nécessaires à son
                fonctionnement (session, panier invité, préférences de langue).
                Aucun cookie publicitaire tiers n'est utilisé à ce jour. Vous
                pouvez à tout moment configurer votre navigateur pour refuser
                les cookies, au risque de dégrader certaines fonctionnalités.
            </p>
        ),
    },
    {
        title: '9. Modification de cette politique',
        body: (
            <p>
                Cette Politique de confidentialité peut être mise à jour
                périodiquement. Toute modification substantielle vous sera
                communiquée par un moyen approprié (notification sur la
                Plateforme, e-mail).
            </p>
        ),
    },
    {
        title: '10. Contact',
        body: (
            <p>
                Pour toute question relative à vos données personnelles,
                contactez-nous à l'adresse{' '}
                <a
                    href="mailto:privacy@hirfaty.com"
                    className="text-terracotta underline underline-offset-4"
                >
                    privacy@hirfaty.com
                </a>
                .
            </p>
        ),
    },
];

export default function Privacy() {
    return (
        <>
            <Head title="Politique de confidentialité" />
            <div className="paper-grain min-h-screen bg-cream">
                <SiteHeader />
                <section className="container pt-14 pb-24 md:pt-20">
                    <p className="eyebrow mb-4">Cadre légal</p>
                    <h1 className="h-editorial text-4xl text-ink md:text-6xl">
                        Politique de confidentialité
                    </h1>
                    <p className="mt-4 max-w-xl text-ink-soft">
                        Dernière mise à jour : juillet 2026. Comment nous
                        collectons, utilisons et protégeons vos données.
                    </p>

                    <div className="mt-14 max-w-3xl space-y-10 border-t border-ink/15 pt-10">
                        {sections.map((s) => (
                            <div key={s.title}>
                                <h2 className="h-editorial mb-3 text-2xl text-ink">
                                    {s.title}
                                </h2>
                                <div className="space-y-3 leading-relaxed text-ink-soft [&_strong]:font-semibold [&_strong]:text-ink">
                                    {s.body}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
                <SiteFooter />
            </div>
        </>
    );
}
