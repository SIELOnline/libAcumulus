<?php
namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Invoice\ConfigInterface;

/**
 * Class Countries
 */
class Countries {

  /**
   * Returns the Acumulus location code for a given country code.
   *
   * See https://apidoc.sielsystems.nl/content/invoice-add for more information
   * about the location code.
   *
   * This function will be deprecated once the locationcode has been removed
   * from the API.
   *
   * @param string $countryCode
   *   ISO 3166-1 alpha-2 country code.
   *
   * @return int
   *   Location code.
   */
  public function getLocationCode($countryCode) {
    if (empty($countryCode)) {
      $result = ConfigInterface::LocationCode_None;
    }
    else if ($this->isNl($countryCode)) {
      $result = ConfigInterface::LocationCode_NL;
    }
    elseif ($this->isEu($countryCode)) {
      $result = ConfigInterface::LocationCode_EU;
    }
    else {
      $result = ConfigInterface::LocationCode_RestOfWorld;
    }
    return $result;
  }

  /**
   * Returns whether the country is the Netherlands.
   *
   * For now only the alpha-2 codes are allowed. Other notations might be added
   * as soon we support a web shop with a different way of storing countries.
   *
   * @param string $countryCode
   *   Case insensitive ISO 3166-1 alpha-2 country code.
   *
   * @return bool
   */
  public function isNl($countryCode) {
    return strtoupper($countryCode) === 'NL';
  }

  /**
   * Returns whether the country is a EU country outside the Netherlands.
   *
   * For now only the alpha-2 codes are allowed. Other notations might be added
   * as soon we support a web shop with a different way of storing countries.
   *
   * @param string $countryCode
   *   Case insensitive ISO 3166-1 alpha-2 country code.
   *
   * @return bool
   */
  public function isEu($countryCode) {
    // Sources:
    // - http://publications.europa.eu/code/pdf/370000en.htm
    // - http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
    // EFTA countries are not part of this list because regarding invoicing they
    // are considered to be outside of the EU.
    $euCountryCodes = array(
      'BE',
      'BG',
      'CZ',
      'DK',
      'DE',
      'EE',
      'IE',
      'EL', // Greece according to the EU
      'ES',
      'FR',
      'GB', // Great Britain/United Kingdom according to ISO.
      'GR', // Greece according to the ISO
      'HR',
      'IT',
      'CY',
      'LV',
      'LT',
      'LU',
      'HU',
      'MT',
      //'NL', // In EU, but here we want "in EU but outside the Netherlands".
      'AT',
      'PL',
      'PT',
      'RO',
      'SI',
      'SK',
      'FI',
      'SE',
      'UK', // Great Britain/United Kingdom according to the EU
    );
    return in_array(strtoupper($countryCode), $euCountryCodes);
  }

  /**
   * Converts EU country codes to their ISO equivalent:
   *
   * The EU has 2 country codes that differ form ISO:
   * - UK instead of GB
   * - EL instead of GR.
   *
   * @param string $countryCode
   *   An EU orISO country code.
   *
   * @return string
   *   The ISO country code.
   */
  public function convertEuCountryCode($countryCode) {
    if ($countryCode === 'EL') {
      $countryCode = 'GR';
    }
    if ($countryCode === 'UK') {
      $countryCode = 'GB';
    }
    return $countryCode;
  }

  /**
   * Returns the dutch name for the given country code.
   *
   * @param $countryCode
   *   ISO country code (2 characters).
   *
   * @return string
   *   The (dutch) name of the country.
   */
  public function getCountryName($countryCode) {
    $countryNames = array(
      'AF' => 'Afghanistan',
      'AX' => 'Åland',
      'AL' => 'Albanië',
      'DZ' => 'Algerije',
      'VI' => 'Amerikaanse Maagdeneilanden',
      'AS' => 'Amerikaans-Samoa',
      'AD' => 'Andorra',
      'AO' => 'Angola',
      'AI' => 'Anguilla',
      'AQ' => 'Antarctica',
      'AG' => 'Antigua en Barbuda',
      'AR' => 'Argentinië',
      'AM' => 'Armenië',
      'AW' => 'Aruba',
      'AU' => 'Australië',
      'AZ' => 'Azerbeidzjan',
      'BS' => 'Bahama\'s',
      'BH' => 'Bahrein',
      'BD' => 'Bangladesh',
      'BB' => 'Barbados',
      'BE' => 'België',
      'BZ' => 'Belize',
      'BJ' => 'Benin',
      'BM' => 'Bermuda',
      'BT' => 'Bhutan',
      'BO' => 'Bolivia',
      'BQ' => 'Bonaire, Sint Eustatius en Saba',
      'BA' => 'Bosnië en Herzegovina',
      'BW' => 'Botswana',
      'BV' => 'Bouveteiland',
      'BR' => 'Brazilië',
      'VG' => 'Britse Maagdeneilanden',
      'IO' => 'Brits Indische Oceaanterritorium',
      'BN' => 'Brunei',
      'BG' => 'Bulgarije',
      'BF' => 'Burkina Faso',
      'BI' => 'Burundi',
      'KH' => 'Cambodja',
      'CA' => 'Canada',
      'CF' => 'Centraal-Afrikaanse Republiek',
      'CL' => 'Chili',
      'CN' => 'China',
      'CX' => 'Christmaseiland',
      'CC' => 'Cocoseilanden',
      'CO' => 'Colombia',
      'KM' => 'Comoren',
      'CG' => 'Congo-Brazzaville',
      'CD' => 'Congo-Kinshasa',
      'CK' => 'Cookeilanden',
      'CR' => 'Costa Rica',
      'CU' => 'Cuba',
      'CW' => 'Curaçao',
      'CY' => 'Cyprus',
      'DK' => 'Denemarken',
      'DJ' => 'Djibouti',
      'DM' => 'Dominica',
      'DO' => 'Dominicaanse Republiek',
      'DE' => 'Duitsland',
      'EC' => 'Ecuador',
      'EG' => 'Egypte',
      'SV' => 'El Salvador',
      'GQ' => 'Equatoriaal-Guinea',
      'ER' => 'Eritrea',
      'EE' => 'Estland',
      'ET' => 'Ethiopië',
      'FO' => 'Faeröer',
      'FK' => 'Falklandeilanden',
      'FJ' => 'Fiji',
      'PH' => 'Filipijnen',
      'FI' => 'Finland',
      'FR' => 'Frankrijk',
      'TF' => 'Franse Zuidelijke en Antarctische Gebieden',
      'GF' => 'Frans-Guyana',
      'PF' => 'Frans-Polynesië',
      'GA' => 'Gabon',
      'GM' => 'Gambia',
      'GE' => 'Georgië',
      'GH' => 'Ghana',
      'GI' => 'Gibraltar',
      'GD' => 'Grenada',
      'GR' => 'Griekenland',
      'GL' => 'Groenland',
      'GP' => 'Guadeloupe',
      'GU' => 'Guam',
      'GT' => 'Guatemala',
      'GG' => 'Guernsey',
      'GN' => 'Guinee',
      'GW' => 'Guinee-Bissau',
      'GY' => 'Guyana',
      'HT' => 'Haïti',
      'HM' => 'Heard en McDonaldeilanden',
      'HN' => 'Honduras',
      'HU' => 'Hongarije',
      'HK' => 'Hongkong',
      'IE' => 'Ierland',
      'IS' => 'IJsland',
      'IN' => 'India',
      'ID' => 'Indonesië',
      'IQ' => 'Irak',
      'IR' => 'Iran',
      'IL' => 'Israël',
      'IT' => 'Italië',
      'CI' => 'Ivoorkust',
      'JM' => 'Jamaica',
      'JP' => 'Japan',
      'YE' => 'Jemen',
      'JE' => 'Jersey',
      'JO' => 'Jordanië',
      'KY' => 'Kaaimaneilanden',
      'CV' => 'Kaapverdië',
      'CM' => 'Kameroen',
      'KZ' => 'Kazachstan',
      'KE' => 'Kenia',
      'KG' => 'Kirgizië',
      'KI' => 'Kiribati',
      'UM' => 'Kleine Pacifische eilanden van de Verenigde Staten',
      'KW' => 'Koeweit',
      'HR' => 'Kroatië',
      'LA' => 'Laos',
      'LS' => 'Lesotho',
      'LV' => 'Letland',
      'LB' => 'Libanon',
      'LR' => 'Liberia',
      'LY' => 'Libië',
      'LI' => 'Liechtenstein',
      'LT' => 'Litouwen',
      'LU' => 'Luxemburg',
      'MO' => 'Macau',
      'MK' => 'Macedonië',
      'MG' => 'Madagaskar',
      'MW' => 'Malawi',
      'MV' => 'Maldiven',
      'MY' => 'Maleisië',
      'ML' => 'Mali',
      'MT' => 'Malta',
      'IM' => 'Man',
      'MA' => 'Marokko',
      'MH' => 'Marshalleilanden',
      'MQ' => 'Martinique',
      'MR' => 'Mauritanië',
      'MU' => 'Mauritius',
      'YT' => 'Mayotte',
      'MX' => 'Mexico',
      'FM' => 'Micronesia',
      'MD' => 'Moldavië',
      'MC' => 'Monaco',
      'MN' => 'Mongolië',
      'ME' => 'Montenegro',
      'MS' => 'Montserrat',
      'MZ' => 'Mozambique',
      'MM' => 'Myanmar',
      'NA' => 'Namibië',
      'NR' => 'Nauru',
      'NL' => 'Nederland',
      'NP' => 'Nepal',
      'NI' => 'Nicaragua',
      'NC' => 'Nieuw-Caledonië',
      'NZ' => 'Nieuw-Zeeland',
      'NE' => 'Niger',
      'NG' => 'Nigeria',
      'NU' => 'Niue',
      'MP' => 'Noordelijke Marianen',
      'KP' => 'Noord-Korea',
      "NO" => 'Noorwegen',
      'NF' => 'Norfolk',
      'UG' => 'Oeganda',
      'UA' => 'Oekraïne',
      'UZ' => 'Oezbekistan',
      'OM' => 'Oman',
      'AT' => 'Oostenrijk',
      'TL' => 'Oost-Timor',
      'PK' => 'Pakistan',
      'PW' => 'Palau',
      'PS' => 'Palestina',
      'PA' => 'Panama',
      'PG' => 'Papoea-Nieuw-Guinea',
      'PY' => 'Paraguay',
      'PE' => 'Peru',
      'PN' => 'Pitcairneilanden',
      'PL' => 'Polen',
      'PT' => 'Portugal',
      'PR' => 'Puerto Rico',
      'QA' => 'Qatar',
      'RE' => 'Réunion',
      'RO' => 'Roemenië',
      'RU' => 'Rusland',
      'RW' => 'Rwanda',
      'BL' => 'Saint-Barthélemy',
      'KN' => 'Saint Kitts en Nevis',
      'LC' => 'Saint Lucia',
      'PM' => 'Saint-Pierre en Miquelon',
      'VC' => 'Saint Vincent en de Grenadines',
      'SB' => 'Salomonseilanden',
      'WS' => 'Samoa',
      'SM' => 'San Marino',
      'SA' => 'Saoedi-Arabië',
      'ST' => 'Sao Tomé en Principe',
      'SN' => 'Senegal',
      'RS' => 'Servië',
      'SC' => 'Seychellen',
      'SL' => 'Sierra Leone',
      'SG' => 'Singapore',
      'SH' => 'Sint-Helena, Ascension en Tristan da Cunha',
      'MF' => 'Sint-Maarten',
      'SX' => 'Sint Maarten',
      'SI' => 'Slovenië',
      'SK' => 'Slowakije',
      'SD' => 'Soedan',
      'SO' => 'Somalië',
      'ES' => 'Spanje',
      'SJ' => 'Spitsbergen en Jan Mayen',
      'LK' => 'Sri Lanka',
      'SR' => 'Suriname',
      'SZ' => 'Swaziland',
      'SY' => 'Syrië',
      'TJ' => 'Tadzjikistan',
      'TW' => 'Taiwan',
      'TZ' => 'Tanzania',
      'TH' => 'Thailand',
      'TG' => 'Togo',
      'TK' => 'Tokelau',
      'TO' => 'Tonga',
      'TT' => 'Trinidad en Tobago',
      'TD' => 'Tsjaad',
      'CZ' => 'Tsjechië',
      'TN' => 'Tunesië',
      'TR' => 'Turkije',
      'TM' => 'Turkmenistan',
      'TC' => 'Turks- en Caicoseilanden',
      'TV' => 'Tuvalu',
      'UY' => 'Uruguay',
      'VU' => 'Vanuatu',
      'VA' => 'Vaticaanstad',
      'VE' => 'Venezuela',
      'AE' => 'Verenigde Arabische Emiraten',
      'US' => 'Verenigde Staten',
      'GB' => 'Verenigd Koninkrijk',
      'VN' => 'Vietnam',
      'WF' => 'Wallis en Futuna',
      'EH' => 'Westelijke Sahara',
      'BY' => 'Wit-Rusland',
      'ZM' => 'Zambia',
      'ZW' => 'Zimbabwe',
      'ZA' => 'Zuid-Afrika',
      'GS' => 'Zuid-Georgia en de Zuidelijke Sandwicheilanden',
      'KR' => 'Zuid-Korea',
      'SS' => 'Zuid-Soedan',
      'SE' => 'Zweden',
      'CH' => 'Zwitserland',
    );
    return isset($countryNames[$countryCode]) ? $countryNames[$countryCode] : $countryCode;
  }

}
