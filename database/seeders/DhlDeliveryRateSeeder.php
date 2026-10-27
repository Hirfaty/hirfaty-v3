<?php

namespace Database\Seeders;

use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use App\Models\DeliveryWeightBand;
use Illuminate\Database\Seeder;

/**
 * DHL Express's card for Hirfaty's account (CREATI DIGITAL FINANCE SARL, DHL
 * Express Morocco, Time Definite — Worldwide Export), in MAD.
 *
 * DHL ships every basket leaving Morocco; Amana keeps Morocco (see
 * DeliveryRateSeeder). The price is DHL's own cost, passed on as is:
 *
 *   grid price for the basket's weight in the country's zone
 *   + a handling surcharge per bulky or heavy piece
 *   + the fuel surcharge, a % of those two
 *   + the 150 MAD local service fee per shipment, with 20% VAT (180 MAD)
 *
 * See WeightPricingService for how the weight and the surcharges are worked
 * out.
 *
 * ⚠ The fuel surcharge starts at 0%: DHL moves it every week, so the desk sets
 * the current figure on the admin delivery page. The handling surcharges are
 * listed on the card as a range (214–225 MAD, 1,060–1,124 MAD for a piece
 * over 70 kg); the top of each range is used so a quote never falls short.
 *
 * Left out of the quote: optional services (Saturday delivery, insurance,
 * signatures…), customs and clearance fees, and the remote-area,
 * elevated-risk and restricted-destination surcharges — they depend on
 * things checkout does not know (a postcode list, the goods' value, DHL's
 * current risk list).
 *
 * Idempotent and additive, like DeliveryRateSeeder: a destination or band
 * already on file is left alone, so the desk's edits survive a re-run.
 */
class DhlDeliveryRateSeeder extends Seeder
{
    public const SLUG = 'dhl';

    /** The 150 MAD "local taxable service" fee, plus 20% VAT. */
    private const SHIPMENT_FEE = 180.00;

    /** Per piece over 100 cm long, over 80 cm on its second side, or 25–70 kg. */
    private const HANDLING_FEE = 225.00;

    /** Per piece over 70 kg, actual or volumetric — instead of the handling fee. */
    private const OVERWEIGHT_FEE = 1124.00;

    /** What a piece with no weight on file is priced at, in kg. */
    private const DEFAULT_PIECE_WEIGHT = 1.0;

    /**
     * Every destination on the card, ISO 3166 code ⇒ [French name, zone].
     *
     * DHL's chart uses a few codes of its own; they are filed under the code
     * a buyer's address carries instead — Bonaire (XB → BQ), Curaçao
     * (XC → CW), Saint-Barthélemy (XY → BL), Sint Maarten (XM → SX), Kosovo
     * (KV → XK). Four entries are part of a country already listed, in the
     * same zone, so the country covers them: the Canary Islands (Spain),
     * Somaliland (Somalia), St. Eustatius (Caribbean Netherlands) and Nevis
     * (St. Kitts).
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const COUNTRIES = [
        'AF' => ['Afghanistan', 4],
        'ZA' => ['Afrique du Sud', 6],
        'AL' => ['Albanie', 6],
        'DZ' => ['Algérie', 3],
        'DE' => ['Allemagne', 2],
        'AD' => ['Andorre', 1],
        'AO' => ['Angola', 7],
        'AI' => ['Anguilla', 7],
        'AG' => ['Antigua-et-Barbuda', 7],
        'SA' => ['Arabie saoudite', 4],
        'AR' => ['Argentine', 7],
        'AM' => ['Arménie', 6],
        'AW' => ['Aruba', 7],
        'AU' => ['Australie', 7],
        'AT' => ['Autriche', 3],
        'AZ' => ['Azerbaïdjan', 7],
        'BS' => ['Bahamas', 7],
        'BH' => ['Bahreïn', 4],
        'BD' => ['Bangladesh', 7],
        'BB' => ['Barbade', 7],
        'BE' => ['Belgique', 1],
        'BZ' => ['Belize', 7],
        'BJ' => ['Bénin', 7],
        'BM' => ['Bermudes', 7],
        'BT' => ['Bhoutan', 7],
        'BY' => ['Biélorussie', 6],
        'BO' => ['Bolivie', 7],
        'BA' => ['Bosnie-Herzégovine', 6],
        'BW' => ['Botswana', 7],
        'BR' => ['Brésil', 7],
        'BN' => ['Brunei', 7],
        'BG' => ['Bulgarie', 6],
        'BF' => ['Burkina Faso', 7],
        'BI' => ['Burundi', 7],
        'KH' => ['Cambodge', 7],
        'CM' => ['Cameroun', 7],
        'CA' => ['Canada', 4],
        'CV' => ['Cap-Vert', 7],
        'CL' => ['Chili', 7],
        'CN' => ['Chine', 5],
        'CY' => ['Chypre', 3],
        'CO' => ['Colombie', 7],
        'KM' => ['Comores', 7],
        'CG' => ['Congo-Brazzaville', 7],
        'CD' => ['Congo-Kinshasa', 7],
        'KP' => ['Corée du Nord', 7],
        'KR' => ['Corée du Sud', 7],
        'CR' => ['Costa Rica', 7],
        'CI' => ['Côte d’Ivoire', 6],
        'HR' => ['Croatie', 6],
        'CU' => ['Cuba', 7],
        'CW' => ['Curaçao', 7],
        'DK' => ['Danemark', 3],
        'DJ' => ['Djibouti', 7],
        'DM' => ['Dominique', 7],
        'EG' => ['Égypte', 4],
        'AE' => ['Émirats arabes unis', 4],
        'EC' => ['Équateur', 7],
        'ER' => ['Érythrée', 7],
        'ES' => ['Espagne', 1],
        'EE' => ['Estonie', 6],
        'SZ' => ['Eswatini', 7],
        'VA' => ['État de la Cité du Vatican', 2],
        'US' => ['États-Unis', 4],
        'ET' => ['Éthiopie', 7],
        'FJ' => ['Fidji', 7],
        'FI' => ['Finlande', 3],
        'FR' => ['France', 1],
        'GA' => ['Gabon', 6],
        'GM' => ['Gambie', 7],
        'GE' => ['Géorgie', 6],
        'GH' => ['Ghana', 7],
        'GI' => ['Gibraltar', 1],
        'GR' => ['Grèce', 3],
        'GD' => ['Grenade', 7],
        'GL' => ['Groenland', 3],
        'GP' => ['Guadeloupe', 7],
        'GU' => ['Guam', 7],
        'GT' => ['Guatemala', 7],
        'GG' => ['Guernesey', 3],
        'GN' => ['Guinée', 7],
        'GQ' => ['Guinée équatoriale', 7],
        'GW' => ['Guinée-Bissau', 7],
        'GY' => ['Guyana', 7],
        'GF' => ['Guyane française', 7],
        'HT' => ['Haïti', 7],
        'HN' => ['Honduras', 7],
        'HK' => ['Hong Kong', 5],
        'HU' => ['Hongrie', 6],
        'KY' => ['Îles Caïmans', 7],
        'CK' => ['Îles Cook', 7],
        'FO' => ['Îles Féroé', 6],
        'FK' => ['Îles Malouines', 3],
        'MP' => ['Îles Mariannes du Nord', 7],
        'MH' => ['Îles Marshall', 7],
        'SB' => ['Îles Salomon', 7],
        'TC' => ['Îles Turques-et-Caïques', 7],
        'VG' => ['Îles Vierges britanniques', 7],
        'VI' => ['Îles Vierges des États-Unis', 7],
        'IN' => ['Inde', 5],
        'ID' => ['Indonésie', 7],
        'IQ' => ['Irak', 7],
        'IR' => ['Iran', 4],
        'IE' => ['Irlande', 3],
        'IS' => ['Islande', 3],
        'IL' => ['Israël', 4],
        'IT' => ['Italie', 2],
        'JM' => ['Jamaïque', 7],
        'JP' => ['Japon', 5],
        'JE' => ['Jersey', 3],
        'JO' => ['Jordanie', 4],
        'KZ' => ['Kazakhstan', 7],
        'KE' => ['Kenya', 7],
        'KG' => ['Kirghizstan', 3],
        'KI' => ['Kiribati', 7],
        'XK' => ['Kosovo', 6],
        'KW' => ['Koweït', 4],
        'RE' => ['La Réunion', 7],
        'LA' => ['Laos', 7],
        'LS' => ['Lesotho', 7],
        'LV' => ['Lettonie', 6],
        'LB' => ['Liban', 4],
        'LR' => ['Liberia', 7],
        'LY' => ['Libye', 7],
        'LI' => ['Liechtenstein', 3],
        'LT' => ['Lituanie', 6],
        'LU' => ['Luxembourg', 2],
        'MO' => ['Macao', 7],
        'MK' => ['Macédoine du Nord', 6],
        'MG' => ['Madagascar', 7],
        'MY' => ['Malaisie', 7],
        'MW' => ['Malawi', 7],
        'MV' => ['Maldives', 7],
        'ML' => ['Mali', 7],
        'MT' => ['Malte', 3],
        'MQ' => ['Martinique', 7],
        'MU' => ['Maurice', 7],
        'MR' => ['Mauritanie', 3],
        'YT' => ['Mayotte', 7],
        'MX' => ['Mexique', 4],
        'FM' => ['Micronésie', 7],
        'MD' => ['Moldavie', 6],
        'MC' => ['Monaco', 1],
        'MN' => ['Mongolie', 7],
        'ME' => ['Monténégro', 6],
        'MS' => ['Montserrat', 7],
        'MZ' => ['Mozambique', 7],
        'MM' => ['Myanmar (Birmanie)', 7],
        'NA' => ['Namibie', 7],
        'NR' => ['Nauru', 7],
        'NP' => ['Népal', 7],
        'NI' => ['Nicaragua', 7],
        'NE' => ['Niger', 7],
        'NG' => ['Nigeria', 7],
        'NU' => ['Niue', 7],
        'NO' => ['Norvège', 3],
        'NC' => ['Nouvelle-Calédonie', 7],
        'NZ' => ['Nouvelle-Zélande', 7],
        'OM' => ['Oman', 4],
        'UG' => ['Ouganda', 7],
        'UZ' => ['Ouzbékistan', 3],
        'PK' => ['Pakistan', 7],
        'PW' => ['Palaos', 7],
        'PA' => ['Panama', 7],
        'PG' => ['Papouasie-Nouvelle-Guinée', 7],
        'PY' => ['Paraguay', 7],
        'NL' => ['Pays-Bas', 2],
        'BQ' => ['Pays-Bas caribéens', 7],
        'PE' => ['Pérou', 7],
        'PH' => ['Philippines', 7],
        'PL' => ['Pologne', 5],
        'PF' => ['Polynésie française', 7],
        'PR' => ['Porto Rico', 7],
        'PT' => ['Portugal', 2],
        'QA' => ['Qatar', 4],
        'CF' => ['République centrafricaine', 7],
        'DO' => ['République dominicaine', 7],
        'RO' => ['Roumanie', 5],
        'GB' => ['Royaume-Uni', 2],
        'RU' => ['Russie', 6],
        'RW' => ['Rwanda', 7],
        'BL' => ['Saint-Barthélemy', 7],
        'KN' => ['Saint-Christophe-et-Niévès', 7],
        'SM' => ['Saint-Marin', 2],
        'SX' => ['Saint-Martin (partie néerlandaise)', 7],
        'VC' => ['Saint-Vincent-et-les Grenadines', 7],
        'SH' => ['Sainte-Hélène', 7],
        'LC' => ['Sainte-Lucie', 7],
        'SV' => ['Salvador', 7],
        'WS' => ['Samoa', 7],
        'AS' => ['Samoa américaines', 7],
        'ST' => ['Sao Tomé-et-Principe', 7],
        'SN' => ['Sénégal', 6],
        'RS' => ['Serbie', 6],
        'SC' => ['Seychelles', 7],
        'SL' => ['Sierra Leone', 7],
        'SG' => ['Singapour', 5],
        'SK' => ['Slovaquie', 6],
        'SI' => ['Slovénie', 6],
        'SO' => ['Somalie', 7],
        'SD' => ['Soudan', 7],
        'SS' => ['Soudan du Sud', 7],
        'LK' => ['Sri Lanka', 7],
        'SE' => ['Suède', 3],
        'CH' => ['Suisse', 3],
        'SR' => ['Suriname', 7],
        'SY' => ['Syrie', 7],
        'TJ' => ['Tadjikistan', 3],
        'TW' => ['Taïwan', 5],
        'TZ' => ['Tanzanie', 7],
        'TD' => ['Tchad', 7],
        'CZ' => ['Tchéquie', 6],
        'TH' => ['Thaïlande', 7],
        'TL' => ['Timor oriental', 7],
        'TG' => ['Togo', 7],
        'TO' => ['Tonga', 7],
        'TT' => ['Trinité-et-Tobago', 7],
        'TN' => ['Tunisie', 3],
        'TM' => ['Turkménistan', 3],
        'TR' => ['Turquie', 3],
        'TV' => ['Tuvalu', 7],
        'UA' => ['Ukraine', 6],
        'UY' => ['Uruguay', 7],
        'VU' => ['Vanuatu', 7],
        'VE' => ['Venezuela', 7],
        'VN' => ['Viêt Nam', 7],
        'YE' => ['Yémen', 7],
        'ZM' => ['Zambie', 7],
        'ZW' => ['Zimbabwe', 7],
    ];

    /**
     * The flat price up to each weight, kg ⇒ [zone 1 … zone 7].
     *
     * @var array<string, array<int, float>>
     */
    private const BRACKETS = [
        '0.5' => [72.08, 100.70, 126.14, 172.78, 190.80, 225.78, 279.84],
        '1.0' => [136.74, 218.36, 283.02, 351.92, 373.12, 426.12, 497.14],
        '1.5' => [192.92, 315.88, 402.80, 493.96, 521.52, 589.36, 676.28],
        '2.0' => [249.10, 413.40, 522.58, 636.00, 669.92, 752.60, 855.42],
        '2.5' => [305.28, 510.92, 642.36, 778.04, 818.32, 915.84, 1034.56],
        '3.0' => [355.10, 595.72, 752.60, 915.84, 959.30, 1072.72, 1211.58],
        '3.5' => [404.92, 680.52, 862.84, 1053.64, 1100.28, 1229.60, 1388.60],
        '4.0' => [454.74, 765.32, 973.08, 1191.44, 1241.26, 1386.48, 1565.62],
        '4.5' => [504.56, 850.12, 1083.32, 1329.24, 1382.24, 1543.36, 1742.64],
        '5.0' => [554.38, 934.92, 1193.56, 1467.04, 1523.22, 1700.24, 1919.66],
        '5.5' => [608.44, 1027.14, 1303.80, 1617.56, 1673.74, 1866.66, 2113.64],
        '6.0' => [662.50, 1119.36, 1414.04, 1768.08, 1824.26, 2033.08, 2307.62],
        '6.5' => [716.56, 1211.58, 1524.28, 1918.60, 1974.78, 2199.50, 2501.60],
        '7.0' => [770.62, 1303.80, 1634.52, 2069.12, 2125.30, 2365.92, 2695.58],
        '7.5' => [824.68, 1396.02, 1744.76, 2219.64, 2275.82, 2532.34, 2889.56],
        '8.0' => [878.74, 1488.24, 1855.00, 2370.16, 2426.34, 2698.76, 3083.54],
        '8.5' => [932.80, 1580.46, 1965.24, 2520.68, 2576.86, 2865.18, 3277.52],
        '9.0' => [986.86, 1672.68, 2075.48, 2671.20, 2727.38, 3031.60, 3471.50],
        '9.5' => [1040.92, 1764.90, 2185.72, 2821.72, 2877.90, 3198.02, 3665.48],
        '10.0' => [1094.98, 1857.12, 2295.96, 2972.24, 3028.42, 3364.44, 3859.46],
    ];

    /**
     * Beyond 10 kg, a price per started step: [up to kg, step kg, [zone 1 …
     * zone 7]]. Each stretch starts where the one before it ends. Together
     * with the brackets these reproduce every published figure from 10.5 to
     * 70 kg exactly.
     *
     * @var array<int, array{0: float, 1: float, 2: array<int, float>}>
     */
    private const STEPS = [
        [20.0, 0.5, [33.92, 66.78, 81.62, 119.78, 125.08, 130.38, 172.78]],
        [30.0, 0.5, [33.92, 63.60, 75.26, 94.34, 94.34, 97.52, 127.20]],
        [70.0, 1.0, [68.90, 83.74, 136.74, 136.74, 136.74, 155.82, 197.16]],
        [300.0, 1.0, [78.44, 89.04, 119.78, 125.08, 130.38, 146.28, 187.62]],
        [99999.0, 1.0, [83.74, 97.52, 131.44, 136.74, 143.10, 161.12, 205.64]],
    ];

    public function run(): void
    {
        $dhl = DeliveryService::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'name' => 'DHL Express',
                'description' => 'Livraison express internationale. Les droits et taxes d’importation éventuels sont à la charge du destinataire.',
                'pricing' => DeliveryService::PRICING_WEIGHT,
                'shipment_fee' => self::SHIPMENT_FEE,
                'fuel_surcharge_percent' => 0,
                'default_piece_weight' => self::DEFAULT_PIECE_WEIGHT,
                'handling_fee' => self::HANDLING_FEE,
                'overweight_fee' => self::OVERWEIGHT_FEE,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        foreach (self::COUNTRIES as $code => [$name, $zone]) {
            DeliveryRate::firstOrCreate(
                [
                    'delivery_service_id' => $dhl->id,
                    'country_code' => $code,
                    'city_id' => null,
                ],
                ['country_name' => $name, 'zone' => $zone, 'fee' => null],
            );
        }

        foreach (range(1, 7) as $zone) {
            foreach (self::BRACKETS as $upTo => $prices) {
                $this->band($dhl, $zone, (float) $upTo, null, $prices[$zone - 1]);
            }

            foreach (self::STEPS as [$upTo, $step, $prices]) {
                $this->band($dhl, $zone, $upTo, $step, $prices[$zone - 1]);
            }
        }
    }

    private function band(DeliveryService $dhl, int $zone, float $upTo, ?float $step, float $price): void
    {
        DeliveryWeightBand::firstOrCreate(
            [
                'delivery_service_id' => $dhl->id,
                'zone' => $zone,
                'up_to' => $upTo,
            ],
            ['step' => $step, 'price' => $price],
        );
    }
}
