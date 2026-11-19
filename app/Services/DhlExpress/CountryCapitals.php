<?php

namespace App\Services\DhlExpress;

/**
 * A town DHL Express knows in each country on its card — the capital, or the
 * main town DHL serves — as DHL spells it.
 *
 * DHL's price abroad depends on the country and the weight (and, for remote
 * areas, the postcode), never on the town, but its rates call refuses a
 * destination it cannot place. Before the buyer has typed a postcode, this
 * town lets checkout ask DHL anyway, so the price shows as soon as the
 * country is picked. Every entry was checked against DHL's rates API on
 * 2026-11-17, in DHL's own spelling where it differs (Thorshavn, Phnum Penh,
 * St Denis). DHL had suspended AF, BY and KP then and does not know XK: those
 * fall back on the rate card.
 */
final class CountryCapitals
{
    public const MAP = [
        'AD' => 'Andorra la Vella', 'AE' => 'Abu Dhabi', 'AF' => 'Kabul', 'AG' => "St. John's",
        'AI' => 'The Valley', 'AL' => 'Tirana', 'AM' => 'Yerevan', 'AO' => 'Luanda',
        'AR' => 'Buenos Aires', 'AS' => 'Pago Pago', 'AT' => 'Vienna', 'AU' => 'Sydney',
        'AW' => 'Oranjestad', 'AZ' => 'Baku', 'BA' => 'Sarajevo', 'BB' => 'Bridgetown',
        'BD' => 'Dhaka', 'BE' => 'Brussels', 'BF' => 'Ouagadougou', 'BG' => 'Sofia',
        'BH' => 'Manama', 'BI' => 'Bujumbura', 'BJ' => 'Cotonou', 'BL' => 'Gustavia',
        'BM' => 'Hamilton', 'BN' => 'Bandar Seri Begawan', 'BO' => 'La Paz', 'BQ' => 'Kralendijk',
        'BR' => 'Sao Paulo', 'BS' => 'Nassau', 'BT' => 'Thimphu', 'BW' => 'Gaborone',
        'BY' => 'Minsk', 'BZ' => 'Belize City', 'CA' => 'Ottawa', 'CD' => 'Kinshasa',
        'CF' => 'Bangui', 'CG' => 'Brazzaville', 'CH' => 'Bern', 'CI' => 'Abidjan',
        'CK' => 'Rarotonga', 'CL' => 'Santiago', 'CM' => 'Douala', 'CN' => 'Beijing',
        'CO' => 'Bogota', 'CR' => 'San Jose', 'CU' => 'Havana', 'CV' => 'Praia',
        'CW' => 'Willemstad', 'CY' => 'Nicosia', 'CZ' => 'Prague', 'DE' => 'Berlin',
        'DJ' => 'Djibouti', 'DK' => 'Copenhagen', 'DM' => 'Roseau', 'DO' => 'Santo Domingo',
        'DZ' => 'Algiers', 'EC' => 'Quito', 'EE' => 'Tallinn', 'EG' => 'Cairo',
        'ER' => 'Asmara', 'ES' => 'Madrid', 'ET' => 'Addis Ababa', 'FI' => 'Helsinki',
        'FJ' => 'Suva', 'FK' => 'Stanley', 'FM' => 'Pohnpei', 'FO' => 'Thorshavn',
        'FR' => 'Paris', 'GA' => 'Libreville', 'GB' => 'London', 'GD' => "St. George's",
        'GE' => 'Tbilisi', 'GF' => 'Cayenne', 'GG' => 'St Peter Port', 'GH' => 'Accra',
        'GI' => 'Gibraltar', 'GL' => 'Nuuk', 'GM' => 'Banjul', 'GN' => 'Conakry',
        'GP' => 'Pointe A Pitre', 'GQ' => 'Malabo', 'GR' => 'Athens', 'GT' => 'Guatemala',
        'GU' => 'Hagatna', 'GW' => 'Bissau', 'GY' => 'Georgetown', 'HK' => 'Hong Kong',
        'HN' => 'Tegucigalpa', 'HR' => 'Zagreb', 'HT' => 'Port-au-Prince', 'HU' => 'Budapest',
        'ID' => 'Jakarta', 'IE' => 'Dublin', 'IL' => 'Tel Aviv', 'IN' => 'New Delhi',
        'IQ' => 'Baghdad', 'IR' => 'Tehran', 'IS' => 'Reykjavik', 'IT' => 'Rome',
        'JE' => 'St Helier', 'JM' => 'Kingston', 'JO' => 'Amman', 'JP' => 'Tokyo',
        'KE' => 'Nairobi', 'KG' => 'Bishkek', 'KH' => 'Phnum Penh', 'KI' => 'Tarawa',
        'KM' => 'Moroni', 'KN' => 'Basseterre', 'KP' => 'Pyongyang', 'KR' => 'Seoul',
        'KW' => 'Kuwait City', 'KY' => 'George Town', 'KZ' => 'Almaty', 'LA' => 'Vientiane',
        'LB' => 'Beirut', 'LC' => 'Castries', 'LI' => 'Vaduz', 'LK' => 'Colombo',
        'LR' => 'Monrovia', 'LS' => 'Maseru', 'LT' => 'Vilnius', 'LU' => 'Luxembourg',
        'LV' => 'Riga', 'LY' => 'Tripoli', 'MC' => 'Monaco', 'MD' => 'Chisinau',
        'ME' => 'Podgorica', 'MG' => 'Antananarivo', 'MH' => 'Majuro', 'MK' => 'Skopje',
        'ML' => 'Bamako', 'MM' => 'Yangon', 'MN' => 'Ulaanbaatar', 'MO' => 'Macau',
        'MP' => 'Saipan', 'MQ' => 'Fort De France', 'MR' => 'Nouakchott', 'MS' => 'Brades',
        'MT' => 'Valletta', 'MU' => 'Port Louis', 'MV' => 'Male', 'MW' => 'Lilongwe',
        'MX' => 'Mexico City', 'MY' => 'Kuala Lumpur', 'MZ' => 'Maputo', 'NA' => 'Windhoek',
        'NC' => 'Noumea', 'NE' => 'Niamey', 'NG' => 'Lagos', 'NI' => 'Managua',
        'NL' => 'Amsterdam', 'NO' => 'Oslo', 'NP' => 'Kathmandu', 'NR' => 'Nauru',
        'NU' => 'Alofi', 'NZ' => 'Auckland', 'OM' => 'Muscat', 'PA' => 'Panama City',
        'PE' => 'Lima', 'PF' => 'Papeete', 'PG' => 'Port Moresby', 'PH' => 'Manila',
        'PK' => 'Karachi', 'PL' => 'Warsaw', 'PR' => 'San Juan', 'PT' => 'Lisbon',
        'PW' => 'Koror', 'PY' => 'Asuncion', 'QA' => 'Doha', 'RE' => 'St Denis',
        'RO' => 'Bucharest', 'RS' => 'Belgrade', 'RU' => 'Moscow', 'RW' => 'Kigali',
        'SA' => 'Riyadh', 'SB' => 'Honiara', 'SC' => 'Mahe', 'SD' => 'Port Sudan',
        'SE' => 'Stockholm', 'SG' => 'Singapore', 'SH' => 'Jamestown', 'SI' => 'Ljubljana',
        'SK' => 'Bratislava', 'SL' => 'Freetown', 'SM' => 'San Marino', 'SN' => 'Dakar',
        'SO' => 'Mogadishu', 'SR' => 'Paramaribo', 'SS' => 'Juba', 'ST' => 'Sao Tome',
        'SV' => 'San Salvador', 'SX' => 'Philipsburg', 'SY' => 'Damascus', 'SZ' => 'Mbabane',
        'TC' => 'Providenciales', 'TD' => "N'Djamena", 'TG' => 'Lome', 'TH' => 'Bangkok',
        'TJ' => 'Dushanbe', 'TL' => 'Dili', 'TM' => 'Ashgabat', 'TN' => 'Tunis',
        'TO' => "Nuku'alofa", 'TR' => 'Istanbul', 'TT' => 'Port of Spain', 'TV' => 'Funafuti',
        'TW' => 'Taipei', 'TZ' => 'Dar es Salaam', 'UA' => 'Kyiv', 'UG' => 'Kampala',
        'US' => 'New York', 'UY' => 'Montevideo', 'UZ' => 'Tashkent', 'VA' => 'Vatican City',
        'VC' => 'Kingstown', 'VE' => 'Caracas', 'VG' => 'Road Town', 'VI' => 'Charlotte Amalie',
        'VN' => 'Hanoi', 'VU' => 'Port Vila', 'WS' => 'Apia', 'XK' => 'Pristina',
        'YE' => 'Sanaa', 'YT' => 'Mamoudzou', 'ZA' => 'Johannesburg', 'ZM' => 'Lusaka',
        'ZW' => 'Harare',
    ];

    public static function for(string $country): ?string
    {
        return self::MAP[strtoupper($country)] ?? null;
    }
}
