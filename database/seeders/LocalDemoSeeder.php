<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Models\User;
use App\Models\Wishlist;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A full demo marketplace for a developer's own machine: 30 accounts covering
 * every role and account state, and 300 products across the subcategories
 * with varied prices, promotions, trade prices, stock, statuses, visibility
 * and variants.
 *
 *     php artisan db:seed --class=LocalDemoSeeder
 *
 * Local only — it refuses to run anywhere else. Every account's password is
 * `password`, and every address is on `demo.hirfaty.test`, so the demo rows
 * are easy to tell apart from anything else in the database. It runs once:
 * a second run stops instead of piling a second catalogue on top (start over
 * with `php artisan migrate:fresh --seed`, then this seeder).
 *
 * Every product is sold both ways — a personal price and a lower pro price,
 * from a pro minimum of at least 2 pieces — with stock to spare, and comes
 * with photographs: two of the craft pictures bundled with the frontend
 * (resources/js/images), matched to its category and copied into the
 * product's own folder on the default disk, exactly where an admin upload
 * would land.
 *
 * Seeded from a fixed number, so every run builds the same catalogue.
 */
class LocalDemoSeeder extends Seeder
{
    private const DOMAIN = 'demo.hirfaty.test';

    private const PASSWORD = 'password';

    private const PRODUCT_COUNT = 300;

    /**
     * Bundled pictures (relative to resources/js/images) per main category,
     * matched on the start of its slug. Anything unmatched uses `*`.
     *
     * @var array<string, array<int, string>>
     */
    private const PHOTOS = [
        'poterie' => ['categories/pottery.jpeg', 'cat-pottery.jpg', 'hero-zellige.jpg'],
        'maroquinerie' => ['categories/lether.jpeg', 'cat-leather.jpg', 'cat-shoes.jpg'],
        'textiles' => ['categories/textile.jpeg', 'cat-textile.jpg', 'hero-textile.jpg'],
        'bois' => ['categories/wood.jpeg', 'cat-wood.jpg', 'cat-home.jpg'],
        'lanterne' => ['categories/lantern.jpeg', 'cat-home.jpg', 'hero-main.jpg'],
        'bijoux' => ['cat-jewelry.jpg', 'cat-accessories.jpg'],
        'peinture' => ['categories/art.jpeg', 'categories/art-vivre.jpeg'],
        '*' => ['categories/art-vivre.jpeg', 'cat-home.jpg', 'cat-accessories.jpg', 'cat-spice.jpg'],
    ];

    private Randomizer $random;

    private string $passwordHash;

    private ?User $admin = null;

    /**
     * Product types per subcategory slug, as CategorySeeder creates them.
     * `kg` and `cm` are the shipping profile's ranges, `axes` the variant
     * options the type can be declined in (the first axis is always used,
     * the second only sometimes), and `step` how much dearer each later value
     * of a dimension axis is.
     *
     * @var array<string, array{nouns: array<int, string>, price: array{int, int}, kg: array{float, float}, cm: array{int, int, int}, fragile?: bool, axes?: array<int, array{type: string, name?: string, values: array<int, string>, step?: float}>}>
     */
    private const TYPES = [
        // ── Poterie & Céramique ─────────────────────────────────────
        'tajines-decoratifs' => ['nouns' => ['Tajine décoratif', 'Tajine de service', 'Tajine à couvercle peint', 'Mini tajine à épices'], 'price' => [90, 650], 'kg' => [0.6, 3.5], 'cm' => [30, 30, 28], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['Ø 15 cm', 'Ø 25 cm', 'Ø 35 cm'], 'step' => 0.35], ['type' => 'color', 'values' => ['Bleu de Fès', 'Vert émeraude', 'Ocre']]]],
        'vaisselle-en-ceramique' => ['nouns' => ['Service à couscous', 'Assiette plate', 'Assiette creuse', 'Saladier', 'Set de six assiettes'], 'price' => [60, 1200], 'kg' => [0.4, 6], 'cm' => [30, 30, 10], 'fragile' => true, 'axes' => [['type' => 'pattern', 'values' => ['Floral', 'Géométrique', 'Arabesque']]]],
        'zellige-mosaique' => ['nouns' => ['Table en zellige', 'Fontaine murale en zellige', 'Dessous de plat en zellige', 'Panneau de mosaïque'], 'price' => [180, 6500], 'kg' => [1.5, 30], 'cm' => [60, 60, 8], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['40 × 40 cm', '60 × 60 cm', '80 × 80 cm'], 'step' => 0.45]]],
        'poteries-de-safi' => ['nouns' => ['Jarre de Safi', 'Plat de Safi', 'Pot à épices de Safi', 'Assiette murale de Safi'], 'price' => [80, 900], 'kg' => [0.5, 5], 'cm' => [30, 30, 25], 'fragile' => true],
        'poteries-de-fes' => ['nouns' => ['Plat de Fès', 'Vase bleu de Fès', 'Bonbonnière de Fès', 'Coupe à fruits de Fès'], 'price' => [90, 1100], 'kg' => [0.5, 4], 'cm' => [30, 30, 20], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['Petit', 'Moyen', 'Grand'], 'step' => 0.3]]],
        'vases-en-terre-cuite' => ['nouns' => ['Vase en terre cuite', 'Amphore en terre cuite', 'Pot à plantes', 'Jarre berbère'], 'price' => [70, 850], 'kg' => [0.8, 8], 'cm' => [25, 25, 45], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['H 30 cm', 'H 50 cm', 'H 70 cm'], 'step' => 0.4]]],
        'bols-plats' => ['nouns' => ['Bol à soupe', 'Plat à tajine', 'Bol à harira', 'Plat de service ovale'], 'price' => [45, 380], 'kg' => [0.3, 2], 'cm' => [25, 25, 10], 'fragile' => true, 'axes' => [['type' => 'color', 'values' => ['Bleu cobalt', 'Vert', 'Jaune safran', 'Blanc cassé']]]],
        'theieres-services-a-the' => ['nouns' => ['Théière marocaine', 'Service à thé complet', 'Théière berrad', 'Plateau à thé garni'], 'price' => [150, 1800], 'kg' => [0.6, 4], 'cm' => [30, 30, 25], 'fragile' => true, 'axes' => [['type' => 'finish', 'values' => ['Argenté', 'Doré', 'Cuivré']], ['type' => 'dimension', 'values' => ['0,6 L', '1 L', '1,5 L'], 'step' => 0.2]]],
        'ceramique-peinte-a-la-main' => ['nouns' => ['Assiette peinte à la main', 'Vase peint à la main', 'Coupe décorative peinte', 'Carreau peint encadré'], 'price' => [90, 950], 'kg' => [0.4, 3], 'cm' => [30, 30, 12], 'fragile' => true, 'axes' => [['type' => 'pattern', 'values' => ['Fleurs de grenade', 'Étoile à huit branches', 'Calligraphie']]]],
        'cendriers-bibelots' => ['nouns' => ['Cendrier en céramique', 'Bibelot en céramique', 'Pot à encens', 'Boîte à bijoux en céramique'], 'price' => [35, 260], 'kg' => [0.2, 1], 'cm' => [15, 15, 8], 'fragile' => true],
        'carreaux-decoratifs' => ['nouns' => ['Lot de carreaux décoratifs', 'Carreau de ciment', 'Frise de carreaux émaillés'], 'price' => [120, 1500], 'kg' => [2, 15], 'cm' => [40, 40, 10], 'fragile' => true, 'axes' => [['type' => 'other', 'name' => 'Quantité', 'values' => ['Lot de 10', 'Lot de 25', 'Lot de 50'], 'step' => 1.3]]],

        // ── Maroquinerie ────────────────────────────────────────────
        'sacs-a-main' => ['nouns' => ['Sac à main en cuir', 'Sac cabas en cuir', 'Sac seau en cuir', 'Sac bandoulière brodé'], 'price' => [280, 1900], 'kg' => [0.4, 1.4], 'cm' => [40, 15, 32], 'axes' => [['type' => 'color', 'values' => ['Naturel', 'Cognac', 'Noir', 'Bordeaux']]]],
        'ceintures-en-cuir' => ['nouns' => ['Ceinture en cuir tanné', 'Ceinture tressée', 'Ceinture à boucle gravée'], 'price' => [120, 520], 'kg' => [0.15, 0.4], 'cm' => [30, 12, 5], 'axes' => [['type' => 'other', 'name' => 'Taille', 'values' => ['85 cm', '95 cm', '105 cm', '115 cm']], ['type' => 'color', 'values' => ['Marron', 'Noir']]]],
        'babouches' => ['nouns' => ['Babouches en cuir', 'Babouches brodées', 'Babouches pointues de Fès', 'Babouches berbères'], 'price' => [140, 480], 'kg' => [0.3, 0.7], 'cm' => [32, 14, 10], 'axes' => [['type' => 'other', 'name' => 'Pointure', 'values' => ['37', '38', '39', '40', '41', '42', '43']], ['type' => 'color', 'values' => ['Jaune safran', 'Blanc', 'Rouge', 'Noir']]]],
        'portefeuilles' => ['nouns' => ['Portefeuille en cuir', 'Porte-cartes en cuir', 'Porte-monnaie brodé'], 'price' => [90, 420], 'kg' => [0.1, 0.25], 'cm' => [20, 12, 3], 'axes' => [['type' => 'color', 'values' => ['Naturel', 'Cognac', 'Noir']]]],
        'sacoches-besaces' => ['nouns' => ['Sacoche en cuir', 'Besace de voyage', 'Cartable en cuir', 'Sac de messager'], 'price' => [320, 2200], 'kg' => [0.6, 2], 'cm' => [42, 12, 32], 'axes' => [['type' => 'color', 'values' => ['Naturel', 'Tabac', 'Noir']]]],
        'poufs-en-cuir' => ['nouns' => ['Pouf en cuir brodé', 'Pouf rond en cuir', 'Pouf carré en cuir'], 'price' => [380, 1600], 'kg' => [1, 3], 'cm' => [55, 55, 20], 'axes' => [['type' => 'color', 'values' => ['Naturel', 'Marron', 'Blanc', 'Bleu nuit']], ['type' => 'dimension', 'values' => ['Ø 50 cm', 'Ø 60 cm'], 'step' => 0.25]]],
        'tapis-en-cuir' => ['nouns' => ['Tapis en cuir tressé', 'Tapis en patchwork de cuir'], 'price' => [900, 5500], 'kg' => [3, 12], 'cm' => [80, 40, 20], 'axes' => [['type' => 'dimension', 'values' => ['120 × 180 cm', '160 × 230 cm', '200 × 300 cm'], 'step' => 0.5]]],
        'coussins-en-cuir' => ['nouns' => ['Coussin en cuir', 'Housse de coussin brodée', 'Coussin de sol en cuir'], 'price' => [150, 700], 'kg' => [0.3, 1.5], 'cm' => [45, 45, 10], 'axes' => [['type' => 'color', 'values' => ['Naturel', 'Cognac', 'Vert olive']]]],
        'etuis-pochettes' => ['nouns' => ['Pochette en cuir', 'Étui à lunettes', 'Trousse de toilette', 'Étui à passeport'], 'price' => [70, 450], 'kg' => [0.1, 0.4], 'cm' => [25, 15, 5], 'axes' => [['type' => 'color', 'values' => ['Naturel', 'Rouge', 'Noir', 'Turquoise']]]],
        'accessoires-en-cuir-grave' => ['nouns' => ['Porte-clés gravé', 'Marque-page en cuir gravé', 'Bracelet en cuir gravé', 'Carnet en cuir gravé'], 'price' => [40, 380], 'kg' => [0.05, 0.5], 'cm' => [22, 15, 3]],
        'sandales-artisanales' => ['nouns' => ['Sandales en cuir', 'Nu-pieds tressés', 'Sandales berbères'], 'price' => [180, 550], 'kg' => [0.4, 0.8], 'cm' => [30, 12, 10], 'axes' => [['type' => 'other', 'name' => 'Pointure', 'values' => ['36', '37', '38', '39', '40', '41', '42']]]],

        // ── Art de la table & célébrations ──────────────────────────
        'nappes-serviettes-brodees' => ['nouns' => ['Nappe brodée main', 'Set de serviettes brodées', 'Chemin de table brodé'], 'price' => [140, 1300], 'kg' => [0.3, 2], 'cm' => [35, 25, 6], 'axes' => [['type' => 'dimension', 'values' => ['150 × 150 cm', '150 × 250 cm', '180 × 300 cm'], 'step' => 0.3], ['type' => 'color', 'values' => ['Écru', 'Blanc', 'Terracotta']]]],
        'plateaux-de-service' => ['nouns' => ['Plateau en laiton ciselé', 'Plateau à thé gravé', 'Plateau en bois de thuya'], 'price' => [150, 1600], 'kg' => [0.6, 4], 'cm' => [50, 50, 6], 'axes' => [['type' => 'dimension', 'values' => ['Ø 40 cm', 'Ø 55 cm', 'Ø 70 cm'], 'step' => 0.35]]],
        'corbeilles-paniers' => ['nouns' => ['Panier en osier', 'Corbeille en palmier doum', 'Panier à pain tressé', 'Panier de plage'], 'price' => [60, 480], 'kg' => [0.2, 1.5], 'cm' => [40, 30, 30], 'axes' => [['type' => 'dimension', 'values' => ['Petit', 'Moyen', 'Grand'], 'step' => 0.3]]],
        'verres-a-the-peints' => ['nouns' => ['Coffret de six verres à thé', 'Verres beldi soufflés', 'Verres à thé peints à la main'], 'price' => [80, 420], 'kg' => [0.6, 1.8], 'cm' => [30, 20, 12], 'fragile' => true, 'axes' => [['type' => 'color', 'values' => ['Doré', 'Multicolore', 'Bleu', 'Vert']]]],
        'decorations-de-mariage' => ['nouns' => ['Amariya miniature', 'Coffret de henné décoré', 'Plateau de dattes de mariage', 'Bougies de cérémonie'], 'price' => [120, 3500], 'kg' => [0.3, 5], 'cm' => [40, 40, 40]],
        'coffrets-cadeaux' => ['nouns' => ['Coffret cadeau hammam', 'Coffret thé et épices', 'Coffret découverte artisanale'], 'price' => [150, 900], 'kg' => [0.5, 2.5], 'cm' => [35, 25, 12], 'axes' => [['type' => 'other', 'name' => 'Formule', 'values' => ['Essentiel', 'Prestige'], 'step' => 0.6]]],
        'bougeoirs-chandeliers' => ['nouns' => ['Bougeoir en laiton', 'Chandelier ajouré', 'Photophore en métal ciselé'], 'price' => [70, 750], 'kg' => [0.3, 2.5], 'cm' => [15, 15, 30], 'axes' => [['type' => 'finish', 'values' => ['Laiton', 'Cuivre', 'Argenté']]]],
        'ustensiles-de-cuisine-en-bois' => ['nouns' => ['Cuillère en bois d’olivier', 'Planche à découper en olivier', 'Mortier en bois', 'Set de spatules en bois'], 'price' => [40, 380], 'kg' => [0.1, 2], 'cm' => [35, 15, 5]],

        // ── Bois & Menuiserie ───────────────────────────────────────
        'meubles-marocains' => ['nouns' => ['Banquette en cèdre', 'Commode peinte', 'Fauteuil sculpté', 'Buffet en cèdre'], 'price' => [2500, 18000], 'kg' => [15, 60], 'cm' => [120, 50, 80], 'axes' => [['type' => 'finish', 'values' => ['Naturel', 'Patiné', 'Peint à la main'], 'step' => 0.15]]],
        'tables-basses-sculptees' => ['nouns' => ['Table basse sculptée', 'Table d’appoint octogonale', 'Table pliante en cèdre'], 'price' => [900, 7500], 'kg' => [6, 25], 'cm' => [80, 80, 45], 'axes' => [['type' => 'dimension', 'values' => ['Ø 60 cm', 'Ø 80 cm', 'Ø 100 cm'], 'step' => 0.35]]],
        'coffrets-en-bois' => ['nouns' => ['Coffret en thuya', 'Boîte à thé en bois', 'Coffret à épices en cèdre'], 'price' => [90, 900], 'kg' => [0.3, 2], 'cm' => [25, 18, 10]],
        'miroirs-en-bois-sculpte' => ['nouns' => ['Miroir en bois sculpté', 'Miroir mural en cèdre', 'Miroir arche en bois'], 'price' => [450, 4800], 'kg' => [3, 15], 'cm' => [70, 5, 100], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['60 × 90 cm', '80 × 120 cm'], 'step' => 0.45]]],
        'portes-moucharabiehs' => ['nouns' => ['Porte en cèdre sculptée', 'Panneau moucharabieh', 'Paravent moucharabieh'], 'price' => [3500, 25000], 'kg' => [20, 80], 'cm' => [100, 10, 200]],
        'jouets-en-bois' => ['nouns' => ['Jeu de dames en bois', 'Toupie en bois', 'Puzzle en bois', 'Camion en bois'], 'price' => [45, 350], 'kg' => [0.2, 1.5], 'cm' => [30, 20, 10]],
        'cadres-en-bois' => ['nouns' => ['Cadre en bois sculpté', 'Cadre en marqueterie', 'Cadre photo peint'], 'price' => [80, 700], 'kg' => [0.4, 2.5], 'cm' => [40, 3, 50], 'axes' => [['type' => 'dimension', 'values' => ['13 × 18 cm', '20 × 30 cm', '30 × 40 cm'], 'step' => 0.3]]],
        'etageres-rangements' => ['nouns' => ['Étagère murale en cèdre', 'Étagère d’angle sculptée', 'Porte-manteau en bois'], 'price' => [350, 3200], 'kg' => [3, 18], 'cm' => [80, 25, 60]],
        'marqueterie-de-essaouira' => ['nouns' => ['Boîte en marqueterie de thuya', 'Plateau en marqueterie', 'Échiquier en marqueterie'], 'price' => [150, 2400], 'kg' => [0.4, 4], 'cm' => [35, 35, 8]],
        'coffrets-a-bijoux' => ['nouns' => ['Coffret à bijoux en thuya', 'Boîte à bagues sculptée', 'Écrin en bois et nacre'], 'price' => [120, 1200], 'kg' => [0.3, 1.5], 'cm' => [22, 15, 10], 'axes' => [['type' => 'material', 'values' => ['Thuya', 'Cèdre', 'Noyer']]]],

        // ── Peinture & Art ──────────────────────────────────────────
        'tableaux-peints-a-la-main' => ['nouns' => ['Tableau de la médina', 'Huile sur toile — souk', 'Aquarelle de Chefchaouen', 'Paysage de l’Atlas'], 'price' => [600, 12000], 'kg' => [1, 6], 'cm' => [80, 5, 60], 'axes' => [['type' => 'dimension', 'values' => ['40 × 50 cm', '60 × 80 cm', '90 × 120 cm'], 'step' => 0.6]]],
        'calligraphie-arabe' => ['nouns' => ['Calligraphie sur papier', 'Calligraphie sur toile', 'Calligraphie personnalisée'], 'price' => [350, 5500], 'kg' => [0.5, 4], 'cm' => [60, 5, 50]],
        'portraits-sur-commande' => ['nouns' => ['Portrait au fusain', 'Portrait à l’huile', 'Portrait de famille'], 'price' => [800, 7000], 'kg' => [0.5, 4], 'cm' => [50, 5, 60], 'axes' => [['type' => 'dimension', 'values' => ['30 × 40 cm', '50 × 70 cm'], 'step' => 0.7]]],
        'art-mural-berbere' => ['nouns' => ['Tenture berbère', 'Art mural en laine', 'Panneau berbère peint'], 'price' => [300, 3500], 'kg' => [0.5, 4], 'cm' => [80, 5, 100]],
        'toiles-abstraites' => ['nouns' => ['Toile abstraite', 'Composition abstraite ocre', 'Triptyque abstrait'], 'price' => [500, 9000], 'kg' => [1, 5], 'cm' => [80, 5, 80]],
        'illustrations-gravures' => ['nouns' => ['Gravure de Tanger', 'Illustration de la médina', 'Linogravure berbère', 'Affiche illustrée'], 'price' => [120, 1500], 'kg' => [0.2, 1.5], 'cm' => [40, 3, 50], 'axes' => [['type' => 'dimension', 'values' => ['A4', 'A3', 'A2'], 'step' => 0.4]]],

        // ── Luminaires & dinanderie d'art ───────────────────────────
        'lanternes-marocaines' => ['nouns' => ['Lanterne en laiton ajouré', 'Lanterne en verre coloré', 'Lanterne de jardin', 'Lanterne de table'], 'price' => [180, 2800], 'kg' => [0.8, 6], 'cm' => [25, 25, 50], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['H 30 cm', 'H 50 cm', 'H 80 cm'], 'step' => 0.45], ['type' => 'finish', 'values' => ['Laiton', 'Cuivre vieilli', 'Noir']]]],
        'suspensions-en-fer-forge' => ['nouns' => ['Suspension en fer forgé', 'Lustre ajouré', 'Suspension étoile'], 'price' => [450, 5200], 'kg' => [1.5, 10], 'cm' => [45, 45, 60], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['Ø 30 cm', 'Ø 45 cm', 'Ø 60 cm'], 'step' => 0.4]]],
        'appliques-murales' => ['nouns' => ['Applique murale ciselée', 'Applique en laiton', 'Applique en verre coloré'], 'price' => [250, 1800], 'kg' => [0.6, 3], 'cm' => [25, 15, 35], 'fragile' => true, 'axes' => [['type' => 'finish', 'values' => ['Laiton', 'Cuivre', 'Argenté']]]],
        'objets-en-cuivre-grave' => ['nouns' => ['Plateau en cuivre gravé', 'Brasero en cuivre', 'Aiguière en cuivre', 'Bassine en cuivre martelé'], 'price' => [180, 2600], 'kg' => [0.6, 6], 'cm' => [40, 40, 25]],
        'lampes-de-chevet-artisanales' => ['nouns' => ['Lampe de chevet en céramique', 'Lampe en peau de chèvre', 'Lampe en laiton ciselé'], 'price' => [220, 1500], 'kg' => [0.8, 3], 'cm' => [25, 25, 45], 'fragile' => true, 'axes' => [['type' => 'color', 'values' => ['Naturel', 'Ocre', 'Bleu', 'Vert']]]],
        'miroirs-en-metal-cisele' => ['nouns' => ['Miroir en métal ciselé', 'Miroir en laiton martelé', 'Miroir rond en os et métal'], 'price' => [400, 4200], 'kg' => [2, 12], 'cm' => [60, 5, 80], 'fragile' => true, 'axes' => [['type' => 'dimension', 'values' => ['Ø 50 cm', 'Ø 70 cm', 'Ø 90 cm'], 'step' => 0.4]]],

        // ── Textiles & Tapis ────────────────────────────────────────
        'tapis-berberes' => ['nouns' => ['Tapis Beni Ouarain', 'Tapis Azilal', 'Tapis Boucherouite', 'Tapis du Haut Atlas'], 'price' => [1200, 22000], 'kg' => [4, 30], 'cm' => [100, 40, 40], 'axes' => [['type' => 'dimension', 'values' => ['120 × 180 cm', '170 × 240 cm', '200 × 300 cm', '250 × 350 cm'], 'step' => 0.55]]],
        'kilims' => ['nouns' => ['Kilim tissé main', 'Kilim du Moyen Atlas', 'Kilim à rayures', 'Hanbel berbère'], 'price' => [700, 9000], 'kg' => [2, 15], 'cm' => [90, 30, 30], 'axes' => [['type' => 'dimension', 'values' => ['100 × 150 cm', '150 × 230 cm', '200 × 300 cm'], 'step' => 0.5]]],
        'caftans-takchitas' => ['nouns' => ['Caftan brodé', 'Takchita de cérémonie', 'Caftan en velours', 'Caftan en soie sabra'], 'price' => [1500, 18000], 'kg' => [0.8, 3], 'cm' => [50, 40, 12], 'axes' => [['type' => 'other', 'name' => 'Taille', 'values' => ['S', 'M', 'L', 'XL']], ['type' => 'color', 'values' => ['Émeraude', 'Bordeaux', 'Bleu roi', 'Ivoire']]]],
        'djellabas-artisanales' => ['nouns' => ['Djellaba en laine', 'Djellaba brodée', 'Djellaba d’été en lin', 'Gandoura en coton'], 'price' => [450, 3800], 'kg' => [0.6, 2], 'cm' => [45, 35, 10], 'axes' => [['type' => 'other', 'name' => 'Taille', 'values' => ['S', 'M', 'L', 'XL', 'XXL']], ['type' => 'color', 'values' => ['Beige', 'Gris', 'Bleu nuit', 'Blanc']]]],
        'foutas-serviettes-hammam' => ['nouns' => ['Fouta en coton tissé', 'Serviette de hammam', 'Drap de plage en fouta', 'Peignoir de hammam'], 'price' => [80, 520], 'kg' => [0.3, 1.2], 'cm' => [35, 25, 6], 'axes' => [['type' => 'color', 'values' => ['Indigo', 'Safran', 'Rose poudré', 'Vert sauge', 'Rayé']]]],
        'echarpes-en-soie' => ['nouns' => ['Écharpe en soie de cactus', 'Foulard tissé main', 'Étole en sabra', 'Châle en laine fine'], 'price' => [150, 950], 'kg' => [0.1, 0.4], 'cm' => [30, 20, 3], 'axes' => [['type' => 'color', 'values' => ['Bleu majorelle', 'Ocre', 'Prune', 'Argent']]]],

        // ── Bijoux & Argenterie ─────────────────────────────────────
        'bijoux-en-argent' => ['nouns' => ['Parure en argent', 'Pendentif en argent', 'Main de Fatma en argent', 'Khamsa ciselée'], 'price' => [180, 3200], 'kg' => [0.05, 0.3], 'cm' => [12, 10, 4], 'axes' => [['type' => 'finish', 'values' => ['Argent poli', 'Argent oxydé']]]],
        'colliers-berberes' => ['nouns' => ['Collier berbère en ambre', 'Collier de corail', 'Collier tazerzit', 'Collier en perles d’argent'], 'price' => [250, 4800], 'kg' => [0.05, 0.4], 'cm' => [15, 12, 4]],
        'bracelets-graves' => ['nouns' => ['Bracelet gravé en argent', 'Manchette berbère', 'Jonc ciselé', 'Bracelet en émail'], 'price' => [140, 2200], 'kg' => [0.03, 0.2], 'cm' => [10, 10, 4], 'axes' => [['type' => 'other', 'name' => 'Taille', 'values' => ['S', 'M', 'L']]]],
        'boucles-doreilles-artisanales' => ['nouns' => ['Boucles d’oreilles berbères', 'Créoles ciselées', 'Pendants en filigrane'], 'price' => [90, 1300], 'kg' => [0.02, 0.1], 'cm' => [10, 8, 3], 'axes' => [['type' => 'finish', 'values' => ['Argent', 'Plaqué or']]]],
        'bagues-en-argent' => ['nouns' => ['Bague en argent', 'Chevalière gravée', 'Bague berbère à pierre', 'Bague en filigrane'], 'price' => [120, 1800], 'kg' => [0.02, 0.08], 'cm' => [8, 8, 4], 'axes' => [['type' => 'other', 'name' => 'Taille', 'values' => ['50', '52', '54', '56', '58', '60']]]],
        'broches-fibules' => ['nouns' => ['Fibule berbère', 'Broche en argent', 'Paire de tizerzai'], 'price' => [160, 2600], 'kg' => [0.03, 0.3], 'cm' => [12, 10, 4]],
    ];

    /**
     * Words to finish a product name with, per main category slug. Main
     * categories the admin may have renamed fall back to the generic list.
     *
     * @var array<string, array<int, string>>
     */
    private const QUALIFIERS = [
        'poterie-ceramique' => ['bleu de Fès', 'à motifs floraux', 'vert émeraude', 'émaillé', 'aux arabesques', 'ocre et blanc', 'à l’étoile', 'cobalt'],
        'maroquinerie' => ['en cuir de chèvre', 'brodé main', 'tanné à Fès', 'à franges', 'en cuir camel', 'à motifs berbères'],
        'textiles-tissage' => ['de fête', 'doré', 'à l’ancienne', 'traditionnel', 'ciselé', 'de Meknès'],
        'bois-menuiserie' => ['en cèdre de l’Atlas', 'en thuya', 'sculpté main', 'en noyer', 'à incrustations de nacre'],
        'peinture-art' => ['— série Médina', '— série Atlas', 'aux tons ocre', 'en bleu majorelle', 'original signé'],
        'lanterne-marocaine' => ['ajouré', 'ciselé main', 'en laiton martelé', 'aux verres colorés', 'vieilli'],
        'textiles-tapis' => ['tissé main', 'en laine naturelle', 'aux motifs berbères', 'teint au safran', 'du Moyen Atlas'],
        'bijoux-argenterie' => ['en argent 925', 'à corail', 'à émail de Tiznit', 'filigrané', 'à l’ambre'],
        '*' => ['fait main', 'traditionnel', 'artisanal'],
    ];

    /** Where each kind of shop sits, by main category slug. */
    private const CRAFT_CITIES = [
        'poterie-ceramique' => ['Fès', 'Safi'],
        'maroquinerie' => ['Fès', 'Marrakech'],
        'textiles-tissage' => ['Meknès', 'Rabat'],
        'bois-menuiserie' => ['Essaouira', 'Salé'],
        'peinture-art' => ['Tanger', 'Casablanca'],
        'lanterne-marocaine' => ['Marrakech', 'Fès'],
        'textiles-tapis' => ['Khénifra', 'Azilal'],
        'bijoux-argenterie' => ['Tiznit', 'Essaouira'],
    ];

    private const REJECTION_REASONS = [
        'Les photos sont floues : merci de nous en envoyer de nouvelles, sur fond clair.',
        'Le prix proposé ne correspond pas au marché pour cette pièce.',
        'Description incomplète : il manque les dimensions et la matière.',
        'Cette pièce semble industrielle, nous avons besoin d’une preuve de fabrication artisanale.',
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->error('LocalDemoSeeder only runs in the local environment.');

            return;
        }

        if (User::where('email', 'like', '%@'.self::DOMAIN)->exists()) {
            $this->command->warn('Demo accounts already exist — nothing seeded. Start over with:');
            $this->command->line('  php artisan migrate:fresh --seed && php artisan db:seed --class=LocalDemoSeeder');

            return;
        }

        if (Category::count() === 0 || City::count() === 0) {
            $this->call([CitySeeder::class, CategorySeeder::class]);
        }

        $this->random = new Randomizer(new Mt19937(20261110));
        $this->passwordHash = Hash::make(self::PASSWORD);

        DB::transaction(function () {
            $this->seedAdmins();
            $stores = $this->seedSellers();
            $buyers = $this->seedBuyers();
            $products = $this->seedProducts($stores);
            $this->seedWishlistsAndCarts($buyers, $products);
        });

        Category::forgetNavCache();

        $this->report();
    }

    // ── Accounts ─────────────────────────────────────────────────────────

    private function seedAdmins(): void
    {
        $this->admin = $this->user('admin1', 'Salma', 'Bennani', 'super_admin', daysAgo: 300);
        $this->user('admin2', 'Hamza', 'Idrissi', 'super_admin', daysAgo: 200);
    }

    /**
     * Ten sellers: eight shops that can sell (one of them run by a seller
     * who has since been banned, which takes the whole shop off sale), one
     * waiting for verification and one turned down.
     *
     * @return Collection<int, array{store: Store, main: Category, products: int, draftsOnly: bool}>
     */
    private function seedSellers(): Collection
    {
        $mains = Category::roots()->ordered()->with('children')->get()->keyBy('slug');

        $sellers = [
            // [first, last, store name, type, main slug, products, state]
            ['Youssef', 'Alami', 'Atelier Bab Ftouh', 'artist', 'poterie-ceramique', 60, 'approved'],
            ['Fatima', 'Benali', 'Maison Chouara', 'company', 'maroquinerie', 45, 'approved'],
            ['Aicha', 'Ouhadi', 'Coopérative Tifawin', 'cooperative', 'textiles-tapis', 40, 'approved'],
            ['Mehdi', 'Tazi', 'Dar Thuya', 'artist', 'bois-menuiserie', 35, 'approved'],
            ['Khadija', 'Amrani', 'Nour Lumière', 'company', 'lanterne-marocaine', 35, 'approved'],
            ['Omar', 'Fassi', 'Argent de Tiznit', 'artist', 'bijoux-argenterie', 30, 'approved'],
            ['Nadia', 'Berrada', 'Table de Meknès', 'cooperative', 'textiles-tissage', 30, 'approved'],
            ['Rachid', 'Moussaoui', 'Galerie Rachid', 'artist', 'peinture-art', 22, 'banned'],
            ['Imane', 'Chraibi', 'Atelier Safi Bleu', 'artist', 'poterie-ceramique', 3, 'pending'],
            ['Karim', 'Lahlou', 'Les Tapis de Lahlou', 'company', 'textiles-tapis', 0, 'rejected'],
        ];

        $stores = collect();

        foreach ($sellers as $i => [$first, $last, $storeName, $type, $mainSlug, $products, $state]) {
            $main = $mains->get($mainSlug) ?? $mains->first();

            $seller = $this->user(
                sprintf('seller%02d', $i + 1),
                $first,
                $last,
                'seller',
                daysAgo: 250 - $i * 12,
                banned: $state === 'banned',
            );

            $approved = in_array($state, ['approved', 'banned'], true);
            $createdAt = CarbonImmutable::now()->subDays(250 - $i * 12);

            $store = Store::create([
                'user_id' => $seller->id,
                'category_id' => $main->id,
                'city_id' => $this->cityFor($mainSlug),
                'type' => $type,
                'store_name' => $storeName,
                'slug' => Str::slug($storeName),
                'description' => $this->storeDescription($storeName, $main),
                'status' => $approved ? 'active' : 'inactive',
                'verification_status' => match ($state) {
                    'pending' => Store::VERIFICATION_PENDING,
                    'rejected' => Store::VERIFICATION_REJECTED,
                    default => Store::VERIFICATION_APPROVED,
                },
                'submitted_at' => $createdAt->addDay(),
                'approved_at' => $approved ? $createdAt->addDays(3) : null,
                'approved_by' => $state === 'pending' ? null : $this->admin->id,
                'rejection_reason' => $state === 'rejected'
                    ? 'Les pièces présentées sont importées : Hirfaty ne référence que des créations faites main au Maroc.'
                    : null,
            ]);

            $stores->push([
                'store' => $store,
                'main' => $main,
                'products' => $products,
                // A shop still under review has only half-filed listings.
                'draftsOnly' => $state === 'pending',
            ]);
        }

        return $stores;
    }

    /**
     * Thirteen retail buyers in various states, and five trade accounts.
     *
     * @return Collection<int, User>
     */
    private function seedBuyers(): Collection
    {
        $buyers = collect();

        $retail = [
            // [first, last, locale, state]
            ['Karim', 'Mansouri', 'fr', 'active'],
            ['Sara', 'El Fassi', 'fr', 'active'],
            ['Amine', 'Belkadi', 'ar', 'active'],
            ['Julie', 'Martin', 'fr', 'active'],
            ['James', 'Walker', 'en', 'active'],
            ['Leila', 'Haddad', 'en', 'active'],
            ['Yassine', 'Ouazzani', 'fr', 'unverified'],
            ['Meryem', 'Kettani', 'ar', 'not_onboarded'],
            ['Hicham', 'Saidi', 'fr', 'banned'],
            ['Sophie', 'Dubois', 'fr', 'pro_pending'],
            ['Anas', 'Rami', 'fr', 'pro_pending'],
            ['Laura', 'García', 'en', 'pro_pending'],
            ['Driss', 'Naciri', 'fr', 'pro_declined'],
        ];

        foreach ($retail as $i => [$first, $last, $locale, $state]) {
            $user = $this->user(
                sprintf('buyer%02d', $i + 1),
                $first,
                $last,
                'buyer',
                daysAgo: 180 - $i * 9,
                locale: $locale,
                verified: $state !== 'unverified',
                onboarded: ! in_array($state, ['unverified', 'not_onboarded'], true),
                banned: $state === 'banned',
            );

            if ($state === 'pro_pending') {
                $user->forceFill([
                    'professional_request_status' => 'pending',
                    'professional_request_message' => "Je gère une boutique de décoration et souhaite acheter en volume pour {$last} Déco.",
                    'professional_requested_at' => now()->subDays($this->random->getInt(1, 10)),
                ])->save();
            }

            if ($state === 'pro_declined') {
                $user->forceFill([
                    'professional_request_status' => 'declined',
                    'professional_request_message' => 'Achat pour un usage personnel en grande quantité.',
                    'professional_requested_at' => now()->subDays(30),
                    'professional_reviewed_at' => now()->subDays(28),
                    'professional_reviewed_by' => $this->admin->id,
                    'professional_decline_reason' => 'Le compte professionnel est réservé aux entreprises : merci de nous transmettre votre numéro ICE.',
                ])->save();
            }

            if ($state !== 'banned') {
                $buyers->push($user);
            }
        }

        $trade = [
            // [first, last, verified]
            ['Hotel', 'Riad Yasmine', true],
            ['Concept', 'Store Casa', true],
            ['Galerie', 'Atlas Paris', true],
            ['Restaurant', 'Dar Zitoun', true],
            ['Boutique', 'Déco Lyon', false],
        ];

        foreach ($trade as $i => [$first, $last, $verified]) {
            $user = $this->user(
                sprintf('pro%02d', $i + 1),
                $first,
                $last,
                'professional_buyer',
                daysAgo: 160 - $i * 15,
            );

            if ($verified) {
                $user->forceFill([
                    'professional_verified_at' => now()->subDays(120 - $i * 15),
                    'professional_request_status' => 'approved',
                    'professional_requested_at' => now()->subDays(125 - $i * 15),
                    'professional_reviewed_at' => now()->subDays(120 - $i * 15),
                    'professional_reviewed_by' => $this->admin->id,
                ])->save();
            }

            $buyers->push($user);
        }

        return $buyers;
    }

    private function user(
        string $handle,
        string $first,
        string $last,
        string $role,
        int $daysAgo,
        string $locale = 'fr',
        bool $verified = true,
        bool $onboarded = true,
        bool $banned = false,
    ): User {
        static $phone = 0;
        $phone++;

        $createdAt = now()->subDays($daysAgo);

        $user = User::create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $handle.'@'.self::DOMAIN,
            'phone_number' => sprintf('0661%06d', $phone),
            'password' => $this->passwordHash,
            'role' => $role,
            'locale' => $locale,
            'email_verified_at' => $verified ? $createdAt : null,
            'onboarded_at' => $onboarded ? $createdAt : null,
            'terms_accepted' => true,
            'terms_accepted_at' => $createdAt,
        ]);

        // Not fillable, on purpose: banning is an admin act.
        $user->forceFill([
            'is_banned' => $banned,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $user;
    }

    // ── Products ─────────────────────────────────────────────────────────

    /**
     * Builds every product first and inserts them oldest first, so ids follow
     * listing dates the way they do on a real catalogue ("new arrivals" is
     * read from the id).
     *
     * @param  Collection<int, array{store: Store, main: Category, products: int, draftsOnly: bool}>  $stores
     * @return Collection<int, Product>
     */
    private function seedProducts(Collection $stores): Collection
    {
        $planned = [];

        foreach ($stores as $entry) {
            $subcategories = $entry['main']->children
                ->filter(fn (Category $child) => isset(self::TYPES[$child->slug]))
                ->values();

            if ($subcategories->isEmpty()) {
                continue;
            }

            for ($n = 0; $n < $entry['products']; $n++) {
                $planned[] = [
                    'store' => $entry['store'],
                    'main' => $entry['main'],
                    'category' => $subcategories[$this->random->getInt(0, $subcategories->count() - 1)],
                    'draftsOnly' => $entry['draftsOnly'],
                    'createdAt' => CarbonImmutable::now()
                        ->subDays($this->random->getInt(0, 150))
                        ->subMinutes($this->random->getInt(0, 1439)),
                ];
            }
        }

        usort($planned, fn (array $a, array $b) => $a['createdAt'] <=> $b['createdAt']);

        $products = collect();

        foreach ($planned as $i => $plan) {
            $products->push($this->product($plan, $i + 1));
        }

        return $products;
    }

    /**
     * @param  array{store: Store, main: Category, category: Category, draftsOnly: bool, createdAt: CarbonImmutable}  $plan
     */
    private function product(array $plan, int $number): Product
    {
        $type = self::TYPES[$plan['category']->slug];
        $name = $this->productName($type, $plan['main']->slug);

        $base = $this->price($type['price']);
        $status = $plan['draftsOnly'] ? 'draft' : $this->status();
        $axes = $this->axesFor($type);
        $hasVariants = $axes !== [];

        /*
         * Sold both ways: a personal price (sometimes on sale) and a pro price
         * kept below whatever the public actually pays, so the trade figure
         * always shows as a saving. The pro list starts at 2 pieces or more.
         */
        $sale = $this->chance(0.25) ? $this->round($base * $this->float(0.75, 0.92)) : null;
        $professional = $this->round(($sale ?? $base) * $this->float(0.65, 0.85));
        $minimum = $this->pick([2, 2, 3, 5, 6, 10, 12]);

        [$length, $width, $height] = $type['cm'];
        $jitter = fn (int $cm) => max(1, (int) round($cm * $this->float(0.8, 1.2)));

        $product = Product::create([
            'store_id' => $plan['store']->id,
            'category_id' => $plan['category']->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'sku' => sprintf('DEMO-%04d', $number),
            'description' => $this->productDescription($plan['store'], $plan['main']->slug),
            'base_price' => $base,
            'sale_price' => $sale,
            'professional_price' => $professional,
            'professional_sale_price' => $this->chance(0.15)
                ? $this->round($professional * 0.9)
                : null,
            'min_order_qty_professional' => $minimum,
            // With variants, stock lives on each variant (see Admin\ProductController).
            'stock' => $hasVariants ? 0 : $this->stock($minimum),
            'has_variants' => $hasVariants,
            // Listed on both price lists, so every piece can be bought either way.
            'visibility' => 'both',
            'status' => $status,
            'rejection_reason' => $status === 'rejected' ? $this->pick(self::REJECTION_REASONS) : null,
            'reviewed_by' => in_array($status, ['published', 'rejected', 'archived'], true) ? $this->admin->id : null,
            'reviewed_at' => in_array($status, ['published', 'rejected', 'archived'], true) ? $plan['createdAt']->addHours(6) : null,
            'weight' => round($this->float(...$type['kg']), 3),
            'weight_unit' => 'kg',
            'length' => $jitter($length),
            'width' => $jitter($width),
            'height' => $jitter($height),
            'dimension_unit' => 'cm',
            'is_fragile' => $type['fragile'] ?? false,
            'handling_days' => $this->chance(0.6) ? $this->getInt(1, 10) : null,
        ]);

        $product->forceFill([
            'created_at' => $plan['createdAt'],
            'updated_at' => $plan['createdAt'],
        ])->save();

        if ($hasVariants) {
            $this->variants($product, $axes, $base, $professional, $minimum);
        }

        $this->photos($product, $plan['main']->slug);

        return $product;
    }

    /**
     * One axis always, a second only on some products, and never all of an
     * axis's values — so selectors on the product page differ in size too.
     *
     * @return array<int, array{type: string, name: string, values: array<int, string>, step: float}>
     */
    private function axesFor(array $type): array
    {
        $axes = $type['axes'] ?? [];

        if ($axes === [] || ! $this->chance(0.45)) {
            return [];
        }

        $used = [$axes[0]];

        if (isset($axes[1]) && $this->chance(0.4)) {
            $used[] = $axes[1];
        }

        return array_map(function (array $axis) {
            $values = $axis['values'];
            // Keep the order (sizes stay small → large), drop a few at random.
            $keep = $this->getInt(min(2, count($values)), count($values));
            $values = array_slice($values, 0, $keep);

            return [
                'type' => $axis['type'],
                'name' => $axis['name'] ?? ProductVariantOption::axisFor($axis['type']),
                'values' => $values,
                'step' => $axis['step'] ?? 0.0,
            ];
        }, $used);
    }

    /**
     * Every combination of the chosen axes as its own variant. A dimension
     * step makes each later value dearer; a few variants are switched off.
     * Each one stocks enough for a pro order at the minimum.
     *
     * @param  array<int, array{type: string, name: string, values: array<int, string>, step: float}>  $axes
     */
    private function variants(Product $product, array $axes, float $base, float $professional, int $minimum): void
    {
        $combinations = [[]];

        foreach ($axes as $axis) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($axis['values'] as $index => $value) {
                    $next[] = [...$combination, ['axis' => $axis, 'value' => $value, 'index' => $index]];
                }
            }

            $combinations = $next;
        }

        foreach ($combinations as $position => $options) {
            $multiplier = 1.0;

            foreach ($options as $option) {
                $multiplier *= 1 + $option['axis']['step'] * $option['index'];
            }

            $priced = $multiplier !== 1.0;

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $product->sku.'-'.($position + 1),
                'stock' => $this->stock($minimum),
                'price_override' => $priced ? $this->round($base * $multiplier) : null,
                'professional_price_override' => $priced
                    ? $this->round($professional * $multiplier)
                    : null,
                'position' => $position,
                'is_active' => ! $this->chance(0.06),
            ]);

            foreach ($options as $option) {
                ProductVariantOption::create([
                    'product_variant_id' => $variant->id,
                    'type' => $option['axis']['type'],
                    'name' => $option['axis']['name'],
                    'value' => $option['value'],
                ]);
            }
        }
    }

    private function productName(array $type, string $mainSlug): string
    {
        $noun = $this->pick($type['nouns']);

        if (! $this->chance(0.7)) {
            return $noun;
        }

        return $noun.' '.$this->pick(self::QUALIFIERS[$mainSlug] ?? self::QUALIFIERS['*']);
    }

    private function productDescription(Store $store, string $mainSlug): string
    {
        $craft = match ($mainSlug) {
            'poterie-ceramique' => 'Tournée puis émaillée à la main, cuite au four traditionnel à bois.',
            'maroquinerie' => 'Cuir tanné de façon végétale dans les tanneries de la médina, cousu et teint à la main.',
            'textiles-tapis' => 'Tissée sur un métier vertical par les femmes de la coopérative, en laine filée à la main.',
            'bois-menuiserie' => 'Taillée dans un bois massif séché plusieurs années, puis sculptée et poncée à la main.',
            'lanterne-marocaine' => 'Découpée, ajourée et martelée à la main, motif par motif.',
            'bijoux-argenterie' => 'Ciselée à la main selon des motifs transmis de génération en génération.',
            'peinture-art' => 'Œuvre originale, signée par l’artiste et livrée avec son certificat.',
            default => 'Façonnée à la main, selon des gestes transmis de génération en génération.',
        };

        return '<p>Pièce créée dans l’atelier '.e($store->store_name).'. '.$craft.'</p>'
            .'<p>Chaque pièce est unique : de légères variations de teinte et de forme témoignent du travail artisanal.</p>';
    }

    private function storeDescription(string $storeName, Category $main): string
    {
        return "{$storeName} perpétue le savoir-faire marocain ({$main->translated_name}) : chaque pièce est faite à la main, en petite série, dans notre atelier.";
    }

    private function status(): string
    {
        return $this->weighted([
            'published' => 82,
            'pending_review' => 5,
            'draft' => 4,
            'rejected' => 4,
            'archived' => 5,
        ]);
    }

    /** Enough for several pro orders at the minimum — nothing sold out. */
    private function stock(int $minimum): int
    {
        return $this->getInt(max(20, $minimum * 3), max(60, $minimum * 10));
    }

    /**
     * Two photographs for the piece, copied from the bundled craft pictures
     * that match its main category into `products/{id}/` on the default disk
     * — the folder and the Media row an admin upload would produce, so the
     * edit screen, deletion and the media health checks treat them alike.
     */
    private function photos(Product $product, string $mainSlug): void
    {
        $pool = self::PHOTOS['*'];

        foreach (self::PHOTOS as $prefix => $files) {
            if ($prefix !== '*' && str_starts_with($mainSlug, $prefix)) {
                $pool = $files;

                break;
            }
        }

        $disk = config('filesystems.default');
        $first = $this->getInt(0, count($pool) - 1);

        foreach ([0, 1] as $order) {
            $source = resource_path('js/images/'.$pool[($first + $order) % count($pool)]);
            $extension = pathinfo($source, PATHINFO_EXTENSION);
            $path = "products/{$product->id}/".Str::random(40).'.'.$extension;

            Storage::disk($disk)->put($path, file_get_contents($source), 'public');

            Media::create([
                'mediable_id' => $product->id,
                'mediable_type' => Product::class,
                'type' => 'image',
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
                'disk' => $disk,
                'mime_type' => 'image/jpeg',
                'size' => filesize($source),
                'order' => $order,
            ]);
        }
    }

    // ── Buyers' activity ─────────────────────────────────────────────────

    /**
     * A few wishlisted pieces for most buyers, and a basket for some, so the
     * account pages and the cart are not empty.
     *
     * @param  Collection<int, User>  $buyers
     * @param  Collection<int, Product>  $products
     */
    private function seedWishlistsAndCarts(Collection $buyers, Collection $products): void
    {
        $onSale = $products
            ->filter(fn (Product $p) => $p->status === 'published')
            ->values();

        foreach ($buyers as $i => $buyer) {
            foreach ($this->sample($onSale, $this->getInt(0, 8)) as $product) {
                Wishlist::create(['user_id' => $buyer->id, 'product_id' => $product->id]);
            }

            if ($i % 3 !== 0 || $buyer->email_verified_at === null) {
                continue;
            }

            $cart = Cart::create(['user_id' => $buyer->id]);

            foreach ($this->sample($onSale, $this->getInt(1, 4)) as $product) {
                $variant = $product->has_variants
                    ? $product->variants()->where('is_active', true)->where('stock', '>', 0)->first()
                    : null;

                if ($product->has_variants && ! $variant) {
                    continue;
                }

                if (! $product->has_variants && $product->stock === 0) {
                    continue;
                }

                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    // At the pro minimum, so the basket is valid in either mode.
                    'quantity' => $product->min_order_qty_professional,
                ]);
            }
        }
    }

    // ── Output ───────────────────────────────────────────────────────────

    private function report(): void
    {
        $demo = User::where('email', 'like', '%@'.self::DOMAIN);

        $this->command->info('✅ Demo marketplace seeded — every password is "'.self::PASSWORD.'".');

        $this->command->table(
            ['Email', 'Role', 'State'],
            (clone $demo)->orderBy('id')->get()->map(fn (User $u) => [
                $u->email,
                $u->role,
                $this->describe($u),
            ])->all(),
        );

        $ids = (clone $demo)->where('role', 'seller')->pluck('id');
        $storeIds = Store::whereIn('user_id', $ids)->pluck('id');
        $products = Product::whereIn('store_id', $storeIds);

        $this->command->info(sprintf(
            '%d products (%d published, %d with variants, %d variants) in %d shops.',
            (clone $products)->count(),
            (clone $products)->where('status', 'published')->count(),
            (clone $products)->where('has_variants', true)->count(),
            ProductVariant::whereIn('product_id', (clone $products)->pluck('id'))->count(),
            $storeIds->count(),
        ));
    }

    private function describe(User $user): string
    {
        $state = [];

        if ($user->is_banned) {
            $state[] = 'banned';
        }

        if ($user->email_verified_at === null) {
            $state[] = 'email not verified';
        }

        if ($user->onboarded_at === null && $user->isBuyer()) {
            $state[] = 'not onboarded';
        }

        if ($user->isSeller()) {
            $store = Store::where('user_id', $user->id)->first();
            $state[] = "{$store?->store_name} ({$store?->verification_status})";
        }

        if ($user->isProfessionalBuyer()) {
            $state[] = $user->professional_verified_at ? 'verified trade' : 'trade, not verified';
        } elseif ($user->professional_request_status) {
            $state[] = "trade request {$user->professional_request_status}";
        }

        if ($user->locale !== 'fr') {
            $state[] = "locale {$user->locale}";
        }

        return implode(', ', $state) ?: 'active';
    }

    // ── Randomness ───────────────────────────────────────────────────────

    private function cityFor(string $mainSlug): ?int
    {
        foreach (self::CRAFT_CITIES[$mainSlug] ?? [] as $name) {
            $id = City::where('name', $name)->value('id');

            if ($id) {
                return $id;
            }
        }

        return City::query()->orderBy('id')->value('id');
    }

    /** A price in the range, rounded the way a shop would write it. */
    private function price(array $range): float
    {
        return $this->round($this->float((float) $range[0], (float) $range[1]));
    }

    private function round(float $amount): float
    {
        $step = match (true) {
            $amount < 100 => 5,
            $amount < 1000 => 10,
            default => 50,
        };

        return (float) max($step, round($amount / $step) * $step);
    }

    private function getInt(int $min, int $max): int
    {
        return $this->random->getInt($min, $max);
    }

    private function float(float $min, float $max): float
    {
        return $this->random->getFloat($min, $max);
    }

    private function chance(float $probability): bool
    {
        return $this->random->getFloat(0, 1) < $probability;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }

    /** @param  array<string, int>  $weights */
    private function weighted(array $weights): string
    {
        $roll = $this->random->getInt(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }

    /**
     * @param  Collection<int, Product>  $items
     * @return array<int, Product>
     */
    private function sample(Collection $items, int $count): array
    {
        if ($count === 0 || $items->isEmpty()) {
            return [];
        }

        $keys = $this->random->pickArrayKeys($items->all(), min($count, $items->count()));

        return array_map(fn ($key) => $items[$key], $keys);
    }
}
