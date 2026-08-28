import { Head } from '@inertiajs/react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/site/SiteHeader';

const sections = [
    {
        title: '1. Objet',
        body: (
            <>
                <p>
                    Hirfaty est une place de marché en ligne qui met en relation
                    des artisans marocains (« Vendeurs ») et des particuliers («
                    Acheteurs ») souhaitant découvrir et acquérir des pièces
                    artisanales faites main. Les présentes conditions
                    d'utilisation (les « Conditions ») régissent l'accès et
                    l'utilisation du site et de l'application Hirfaty (la «
                    Plateforme ») par tout utilisateur, quel que soit son rôle.
                </p>
                <p>
                    En créant un compte, en vous connectant ou en utilisant la
                    Plateforme de quelque manière que ce soit, vous reconnaissez
                    avoir lu, compris et accepté sans réserve les présentes
                    Conditions ainsi que notre Politique de confidentialité.
                </p>
            </>
        ),
    },
    {
        title: '2. Inscription et compte utilisateur',
        body: (
            <>
                <p>
                    L'utilisation de certaines fonctionnalités de la Plateforme
                    nécessite la création d'un compte. Vous vous engagez à
                    fournir des informations exactes, complètes et à jour, et à
                    en assurer la confidentialité (identifiants, mot de passe).
                    Vous êtes seul responsable de toute activité effectuée
                    depuis votre compte.
                </p>
                <p>
                    Un compte peut également être créé pour vous par un
                    administrateur Hirfaty, notamment dans le cadre d'un
                    accompagnement à l'inscription. Dans ce cas, vous recevez un
                    lien personnel vous permettant de définir votre mot de passe
                    ; l'acceptation des présentes Conditions reste requise avant
                    tout accès aux fonctionnalités de la Plateforme.
                </p>
            </>
        ),
    },
    {
        title: '3. Rôles et obligations',
        body: (
            <>
                <p>
                    <strong>Acheteurs</strong> — vous vous engagez à fournir des
                    informations de livraison exactes, à régler les commandes
                    passées et à faire un usage loyal des avis et outils de
                    communication mis à disposition.
                </p>
                <p>
                    <strong>Vendeurs (artisans)</strong> — vous êtes seul
                    responsable de l'exactitude des descriptions, prix, stocks,
                    visuels et délais de vos produits, ainsi que de la
                    conformité et de la qualité des pièces vendues. Vos produits
                    sont soumis à une modération par l'équipe Hirfaty avant
                    publication et peuvent être suspendus en cas de
                    non-conformité.
                </p>
                <p>
                    <strong>Administrateurs</strong> — les équipes Hirfaty
                    assurent la modération des contenus, la gestion des comptes
                    et le bon fonctionnement de la Plateforme, dans le respect
                    des présentes Conditions.
                </p>
            </>
        ),
    },
    {
        title: '4. Commandes, frais et livraison',
        body: (
            <>
                <p>
                    Toute commande passée sur la Plateforme constitue un
                    engagement d'achat auprès du Vendeur concerné. Hirfaty agit
                    en qualité d'intermédiaire technique et perçoit une
                    commission sur chaque vente ainsi que des frais de livraison
                    forfaitaires, clairement indiqués avant la validation de la
                    commande.
                </p>
                <p>
                    La livraison des commandes est assurée par notre partenaire
                    logistique Amana sur l'ensemble du territoire marocain. Les
                    délais indiqués sont donnés à titre indicatif et peuvent
                    varier selon la destination et le Vendeur.
                </p>
            </>
        ),
    },
    {
        title: '5. Annulations et retours',
        body: (
            <p>
                Les conditions d'annulation, d'échange et de retour peuvent
                varier selon la nature artisanale et parfois personnalisée des
                produits. En cas de produit non conforme ou endommagé à la
                livraison, contactez notre support afin qu'une solution
                (échange, remboursement) puisse être étudiée avec le Vendeur
                concerné.
            </p>
        ),
    },
    {
        title: '6. Propriété intellectuelle',
        body: (
            <p>
                Les éléments de la Plateforme (marque Hirfaty, logo, charte
                graphique, textes, code) sont protégés par le droit de la
                propriété intellectuelle. Les Vendeurs conservent l'ensemble des
                droits sur leurs créations, visuels et descriptions, et
                garantissent disposer des droits nécessaires pour les publier
                sur la Plateforme.
            </p>
        ),
    },
    {
        title: '7. Comportements interdits',
        body: (
            <p>
                Il est interdit d'utiliser la Plateforme à des fins
                frauduleuses, de publier des contenus illicites, trompeurs,
                contrefaisants ou portant atteinte aux droits d'un tiers, de
                contourner les circuits de paiement de la Plateforme, ou de
                perturber son fonctionnement technique. Tout manquement peut
                entraîner la suspension ou la suppression du compte concerné.
            </p>
        ),
    },
    {
        title: '8. Responsabilité',
        body: (
            <p>
                Hirfaty met tout en œuvre pour assurer la disponibilité et la
                fiabilité de la Plateforme, sans garantir une disponibilité
                ininterrompue. Hirfaty ne saurait être tenue responsable des
                litiges relatifs à la qualité des produits, qui relèvent de la
                responsabilité du Vendeur, sans préjudice de l'accompagnement
                que notre support peut apporter pour faciliter leur résolution.
            </p>
        ),
    },
    {
        title: '9. Suspension et résiliation',
        body: (
            <p>
                Hirfaty se réserve le droit de suspendre ou de résilier l'accès
                d'un utilisateur à la Plateforme en cas de violation des
                présentes Conditions, sans préjudice de toute action appropriée.
                Vous pouvez à tout moment demander la clôture de votre compte
                depuis vos paramètres ou en contactant le support.
            </p>
        ),
    },
    {
        title: '10. Modification des présentes Conditions',
        body: (
            <p>
                Hirfaty peut être amenée à modifier les présentes Conditions,
                notamment pour refléter une évolution de ses services ou de la
                réglementation applicable. Les utilisateurs seront informés de
                tout changement substantiel et pourront, le cas échéant, être
                invités à en accepter à nouveau les termes.
            </p>
        ),
    },
    {
        title: '11. Droit applicable',
        body: (
            <p>
                Les présentes Conditions sont régies par le droit marocain. Tout
                litige relatif à leur interprétation ou à leur exécution relève
                de la compétence exclusive des tribunaux compétents du Royaume
                du Maroc.
            </p>
        ),
    },
    {
        title: '12. Contact',
        body: (
            <p>
                Pour toute question relative aux présentes Conditions, vous
                pouvez nous contacter à l'adresse{' '}
                <a
                    href="mailto:contact@hirfaty.com"
                    className="text-terracotta underline underline-offset-4"
                >
                    contact@hirfaty.com
                </a>
                .
            </p>
        ),
    },
];

export default function Terms() {
    return (
        <>
            <Head title="Conditions d'utilisation" />
            <div className="paper-grain min-h-screen bg-cream">
                <SiteHeader />
                <section className="container pt-14 pb-24 md:pt-20">
                    <p className="eyebrow mb-4">Cadre légal</p>
                    <h1 className="h-editorial text-4xl text-ink md:text-6xl">
                        Conditions d'utilisation
                    </h1>
                    <p className="mt-4 max-w-xl text-ink-soft">
                        Dernière mise à jour : juillet 2026. Merci de lire
                        attentivement ces conditions avant d'utiliser Hirfaty.
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
