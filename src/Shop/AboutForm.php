<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTimeImmutable;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

use function count;
use function is_string;

/**
 * Provides the About block that is shown on most of our forms.
 */
class AboutForm
{
    protected Translator $translator;
    protected ShopCapabilities $shopCapabilities;
    protected Config $acumulusConfig;
    protected Environment $environment;
    protected Acumulus $acumulusApiClient;

    public function __construct(
        Acumulus $acumulusApiClient,
        ShopCapabilities $shopCapabilities,
        Config $acumulusConfig,
        Environment $environment,
        Translator $translator
    ) {
        $this->acumulusApiClient = $acumulusApiClient;
        $this->shopCapabilities = $shopCapabilities;
        $this->acumulusConfig = $acumulusConfig;
        $this->environment = $environment;
        $this->translator = $translator;
    }

    /**
     * Loads the translations for the info block.
     */
    protected function loadAboutFormTranslations(): void
    {
        static $translationsAdded = false;
        if (!$translationsAdded) {
            $this->translator->add(new AboutFormTranslations());
            $translationsAdded = true;
        }
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     *
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    /**
     * @return \Siel\Acumulus\Config\ShopCapabilities
     */
    protected function getShopCapabilities(): ShopCapabilities
    {
        return $this->shopCapabilities;
    }

    /**
     * @return \Siel\Acumulus\Config\Config
     */
    protected function getAcumulusConfig(): Config
    {
        return $this->acumulusConfig;
    }

    /**
     * @return \Siel\Acumulus\ApiClient\Acumulus
     */
    protected function getAcumulusApiClient(): Acumulus
    {
        return $this->acumulusApiClient;
    }

    /**
     * @return \Siel\Acumulus\Config\Environment
     */
    protected function getEnvironment(): Environment
    {
        return $this->environment;
    }

    /**
     * Returns the "About" block.
     *
     *
     * @param bool|null $accountStatus
     *   null: no account data set.
     *   false: incorrect account data set.
     *   true: correct account data set.
     * @param string $wrapperType
     *   The type of wrapper around this block: 'fieldset' or 'details'.
     *
     * @return array[]
     *   The set of version related informational fields.
     *
     * @todo: sanitise external data (i.e. data coming from server)
     */
    public function getAboutBlock(?bool $accountStatus, string $wrapperType): array
    {
        $this->loadAboutFormTranslations();
        $fields = [];

        $contract = $this->getContractList($accountStatus);
        $contractMsg = array_shift($contract);
        $contractContact = array_pop($contract);
        $fields['contractHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . $this->t('contract') . '</h3>',
        ];
        $fields['contractInformation'] = [
            'type' => 'markup',
            'value' => $contractMsg . $this->arrayToList($contract, true),
        ];

        $proSupportList = $this->getProSupportList($accountStatus);
        if ($accountStatus) {
            $fields['proSupportListHeader'] = [
                'type' => 'markup',
                'value' => '<h3>' . $this->t('pro_support_list_header') . '</h3>',
            ];
            $fields['proSupportListInformation'] = [
                'type' => 'markup',
                'value' => $this->arrayToList($proSupportList, true),
            ];

            [$euCommerceProgressBar, $euCommerceMessage] = $this->getEuCommerceInfo($accountStatus);
            if (!empty($euCommerceMessage)) {
                $fields['euCommerceHeader'] = [
                    'type' => 'markup',
                    'value' => '<h3>' . $this->t('euCommerce') . '</h3>',
                ];
                $fields['euCommerceInformation'] = [
                    'type' => 'markup',
                    'value' => "<p>$euCommerceProgressBar<br>$euCommerceMessage</p>",
                ];
            }
        }

        $fields['proSupportHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . $this->t('pro_support_header') . '</h3>',
        ];
        $fields['proSupportInformation'] = [
            'type' => 'markup',
            'value' => sprintf(
                $this->t('pro_support_info'),
                $this->getShopCapabilities()->getLink('pro-support-image'),
                $this->getShopCapabilities()->getLink('pro-support-link'),
                $this->getShopCapabilities()->getLink('activate'),
                $this->t('module')
            ),
        ];

        $environment = $this->getEnvironment()->get();
        $environmentLines = $this->getEnvironment()->getAsLines();
        $fields['environmentHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . $this->t('about_environment') . '</h3>',
        ];
        $fields['environmentInformation'] = [
            'type' => 'markup',
            'value' => $this->arrayToList($environmentLines, true) .
                sprintf($this->t('desc_environmentInformation'), $this->t('module')),
        ];

        $support = $environment['supportEmail'];
        $subject = sprintf($this->t('support_subject'), $environment['shopName'], $this->t('module'));
        $body = sprintf(
            "%s:\n%s%s%s%s:\n%s%s\n%s\n%s\n",
            $this->t('contract'),
            $contractMsg,
            $this->arrayToList($contract, false),
            $this->arrayToList($proSupportList, false),
            $this->t('about_environment'),
            $this->arrayToList($environmentLines, false),
            $this->t('support_body'),
            $this->t('regards'),
            $contractContact
        );
        $moreAcumulus = [
            $this->t('link_login') . '.',
            $this->t('link_mobile') . '.',
            $this->t('link_app') . '.',
            $this->t('link_manual') . '.',
            $this->t('link_website') . '.',
            sprintf($this->t('link_buy_support'), $this->t('module')) . '.',
            $this->t('link_forum') . '.',
            sprintf($this->t('link_support'), rawurldecode($support), rawurlencode($subject), rawurlencode($body)) . '.',
        ];
        $fields['moreAcumulusHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . $this->t('moreAcumulusTitle') . '</h3>',
        ];
        $fields['moreAcumulusInformation'] = [
            'type' => 'markup',
            'value' => $this->arrayToList($moreAcumulus, true),
        ];

        $wrapperTitleType = $wrapperType === 'details' ? 'summary' : 'legend';
        return [
            'type' => $wrapperType,
            $wrapperTitleType => sprintf($this->t('informationBlockHeader'), $this->t('module')),
            'description' => sprintf($this->t('informationBlockDescription'), $this->t('module')),
            'fields' => $fields,
        ];
    }

    /**
     * @param bool|null $accountStatus
     *   null: no account data set.
     *   false: incorrect account data set.
     *   true: correct account data set.
     *
     * @return string[]
     *   Array of strings with:
     *   - 0: message indicating contract status, empty if all correct.
     *   - set of info lines keyed by their label.
     *   - last index: name known for the contract or a general string like
     *     '[your name]'.
     *
     * @noinspection InvertedIfElseConstructsInspection
     */
    protected function getContractList(?bool $accountStatus): array
    {
        $contractContact = $this->t('your_name');
        $myData = $this->getMyData($accountStatus);
        if ($myData === null) {
            $contract = [$this->t('no_contract_data_local')];
        } elseif ($myData === false) {
            $contract = [$this->t('no_contract_data')];
        } else {
            $contract = [''];
            $contract[$this->t('field_code')] = $myData['mycontractcode'] ?? $this->t('unknown');
            $contract[$this->t('field_companyName')] = $myData['mycompanyname'] ?? $this->t('unknown');
            if (!empty($myData['mycontractenddate'])) {
                $endDate = DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $myData['mycontractenddate']);
                if ($endDate) {
                    $now = new DateTimeImmutable();
                    $days = $now->diff($endDate)->days;
                    if ($days < 40) {
                        $contract[$this->t('contract_end_date')] = $endDate->format('j F Y');
                    }
                }
            }
            /** @noinspection TypeUnsafeComparisonInspection */
            if ($myData['mymaxentries'] != -1) {
                $contract[$this->t('entries_about')] = sprintf(
                    $this->t('entries_numbers'),
                    $myData['myentries'],
                    $myData['mymaxentries'],
                    $myData['myentriesleft']
                );
            }
            if ($myData['myemailstatusid'] !== '0') {
                if ($this->translator->getLanguage() === 'nl' && !empty($myData['myemailstatus_nl'])) {
                    $reason = $myData['myemailstatus_nl'];
                } elseif ($this->translator->getLanguage() === 'en' && !empty($myData['myemailstatus_en'])) {
                    $reason = $myData['myemailstatus_en'];
                } elseif (!empty($myData['myemailstatus'])) {
                    $reason = $myData['myemailstatus'];
                } else {
                    $reason = '';
                }
                $contract[$this->t('email_status_label')] = !empty($reason)
                    ? sprintf($this->t('email_status_text_reason'), $reason)
                    : $contract[$this->t('email_status_label')] = $this->t('email_status_text');
            }
            if (!empty($myData['mycontactperson'])) {
                $contractContact = $myData['mycontactperson'];
            }
        }
        $contract[] = $contractContact;
        return $contract;
    }

    /**
     * @param bool|null $accountStatus
     *   null: no account data set.
     *   false: incorrect account data set.
     *   true: correct account data set.
     *
     * @return array
     *   A, possibly empty, list of active support tokens. Format will be
     *   something like: "Pro-support Acumulus-plugin WooCommerce for myshop.com
     *   from 2022-06-03 to 2023-06-05."
     *
     * @todo: expired support seems to be present in the mysupport response value anyway.
     */
    protected function getProSupportList(?bool $accountStatus): array
    {
        $proSupportList = [];
        $myData = $this->getMyData($accountStatus);
        if ($myData === null) {
            $proSupportList[] = $this->t('no_contract_data_local');
        } elseif ($myData === false) {
            $proSupportList[] = $this->t('no_contract_data');
        } elseif (empty($myData['mysupport'])) {
            $proSupportList[] = $this->t('no_pro_support');
        } else {
            $mySupportItems = $myData['mysupport']['item'];
            if (is_string(key($mySupportItems))) {
                // 1 item: make it an array with 1 item
                $mySupportItems = [$mySupportItems];
            }
            usort($mySupportItems, static function ($a, $b) {
                return [$a['startdate'], $a['location']] <=> [$b['startdate'], $b['location']];
            });
            foreach ($mySupportItems as $mySupportItem) {
                $proSupportList[] = sprintf(
                    $this->t('pro_support_line'),
                    $mySupportItem['description'],
                    $mySupportItem['location'],
                    $mySupportItem['startdate'],
                    $mySupportItem['enddate']
                );
            }
        }
        return $proSupportList;
    }

    /**
     * @param bool|null $accountStatus
     *   null: no account data set.
     *   false: incorrect account data set.
     *   true: correct account data set.
     *
     * @return array
     *
     * @noinspection InvertedIfElseConstructsInspection
     */
    protected function getEuCommerceInfo(?bool $accountStatus): array
    {
        $warningPercentage = $this->getAcumulusConfig()->getInvoiceSettings()['euCommerceThresholdPercentage'];
        $percentage = '0';
        if ($warningPercentage !== '') {
            if ($accountStatus === null) {
                $euCommerceProgressBar = $this->addProgressBar($this->t('unknown'), $this->t('unknown'), $percentage, 'warning');
                $euCommerceMessage = $this->t('no_contract_data_local');
            } elseif ($accountStatus === false) {
                $euCommerceProgressBar = $this->addProgressBar($this->t('unknown'), $this->t('unknown'), $percentage, 'warning');
                $euCommerceMessage = $this->t('no_contract_data');
            } else {
                $euCommerceReport = $this->getAcumulusApiClient()->reportThresholdEuCommerce();
                if (!$euCommerceReport->hasError()) {
                    $euCommerceReport = $euCommerceReport->getMainAcumulusResponse();
                    /** @noinspection TypeUnsafeComparisonInspection */
                    $reached = $euCommerceReport['reached'] == 1;
                    $nlTaxed = sprintf('%.0f', $euCommerceReport['nltaxed']);
                    $threshold = sprintf('%.0f', $euCommerceReport['threshold']);
                    $percentage = min($nlTaxed / $threshold * 100.0, 100.0);
                    if ($reached) {
                        $message = $this->t('info_block_eu_commerce_threshold_passed');
                        $status = 'error';
                    } elseif ($percentage >= $warningPercentage) {
                        $message = sprintf($this->t('info_block_eu_commerce_threshold_warning'), $percentage);
                        $status = 'warning';
                    } else {
                        $message = $this->t('info_block_eu_commerce_threshold_ok');
                        $status = 'ok';
                    }
                    $percentage = (string) (int) round($percentage);
                    $euCommerceProgressBar = $this->addProgressBar($nlTaxed, $threshold, $percentage, $status);
                    $euCommerceMessage = $message;
                } else {
                    $euCommerceProgressBar = $this->addProgressBar($this->t('unknown'), $this->t('unknown'), $percentage, 'error');
                    $euCommerceMessage = $this->t('no_eu_commerce_data') . "\n";
                    $euCommerceMessage .= $this->arrayToList(
                        $euCommerceReport->formatMessages(Message::Format_PlainWithSeverity, Severity::RealMessages),
                        true
                    );
                }
            }
        }
        return [$euCommerceProgressBar ?? '', $euCommerceMessage ?? ''];
    }

    /**
     * Returns the HTML for a progress bar indicating the  progress of sales at
     * NL taxed compared to the EU threshold.
     * @param string $nlTaxed
     *   The amount of sales taxed with Dutch tax, or 'unknown'.
     * @param string $threshold
     *   The threshold at which a seller has to switch to EU tax, or 'unknown'.
     *   Note: this threshold is currently 10.000,-€, but is queried from the
     *   API, not hard coded, so it may be 'unknown'.
     * @param string $percentage
     *   The ratio of $nlTaxed / $threshold, as a whole number (between 0 and
     *   100), or 'unknown'.
     * @param string $status
     *   'ok', 'warning', or 'error'.
     *
     * @return string
     */
    protected function addProgressBar(string $nlTaxed, string $threshold, string $percentage, string $status): string
    {
        return "<span class='acumulus-progressbar'><span class='acumulus-progress acumulus-$status' style='min-width:$percentage%'>"
            . "$nlTaxed €</span></span><span class='acumulus-threshold'>$threshold €</span>";
    }

    /**
     * Converts an array with texts to a(n HTML) list.
     *
     * @param string[] $list
     *   List of strings, if the key is s a string, it serves as a
     *   (translatable) label.
     * @param bool $isHtml
     *   Return HTML or plain text.
     */
    protected function arrayToList(array $list, bool $isHtml): string
    {
        /** @noinspection DuplicatedCode  also used in CrashReporter::arrayToList */
        $result = '';
        if (count($list) !== 0) {
            foreach ($list as $key => $line) {
                if (is_string($key) && !ctype_digit($key)) {
                    $key = $this->t($key);
                    $line = "$key: $line";
                }
                $result .= $isHtml ? "<li>$line</li>" : "• $line";
                $result .= "\n";
            }
            if ($isHtml) {
                $result = "<ul>$result</ul>";
            }
            $result .= "\n";
        }
        return $result;
    }

    /**
     * @param bool|null $accountStatus
     *   null: no account data set.
     *   false: incorrect account data set.
     *   true: correct account data set.
     *
     * @return array|false|null
     *   If $accountStatus = true, the my_data array as returned from the
     *   my_acumulus web API call, the $accountStatus otherwise.
     */
    public function getMyData(?bool $accountStatus)
    {
        static $myData = null;
        if ($myData === null) {
            $myData = $accountStatus === true
                ? $this->getAcumulusApiClient()->getMyAcumulus()->getMainAcumulusResponse()
                : $accountStatus;
        }
        return $myData;
    }
}
