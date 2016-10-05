<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Web\Service;

/**
 * Provides basic config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - systemValidate()
 * - isSubmitted()
 * - setSubmittedValues()
 */
abstract class BaseConfigForm extends Form
{
    /** @var \Siel\Acumulus\Shop\ShopCapabilitiesInterface */
    protected $shopCapabilities;

    /** @var \Siel\Acumulus\Shop\Config */
    protected $acumulusConfig;

    /** @var \Siel\Acumulus\Web\Service*/
    protected $service;

    /**
     * @var array
     *   Contact types picklist result, used to test the connection, storing it
     *   in this property prevents another webservice call.
     */
    protected $contactTypes;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     * @param ShopCapabilitiesInterface $shopCapabilities
     * @param \Siel\Acumulus\Web\Service $service
     * @param Config $config
     */
    public function __construct(TranslatorInterface $translator, ShopCapabilitiesInterface $shopCapabilities, Config $config, Service $service)
    {
        parent::__construct($translator);

        $translations = new ConfigFormTranslations();
        $this->translator->add($translations);

        $this->acumulusConfig = $config;
        $this->shopCapabilities = $shopCapabilities;
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     *
     * This is the set of values as are stored in the config.
     */
    protected function getDefaultFormValues()
    {
        return $this->acumulusConfig->getCredentials() + $this->acumulusConfig->getShopSettings() + $this->acumulusConfig->getShopEventSettings() + $this->acumulusConfig->getCustomerSettings() + $this->acumulusConfig->getInvoiceSettings() + $this->acumulusConfig->getEmailAsPdfSettings() + $this->acumulusConfig->getOtherSettings();
    }

    /**
     * {@inheritdoc}
     *
     * Saves the submitted and validated form values in the configuration store.
     */
    protected function execute()
    {
        return $this->acumulusConfig->save($this->submittedValues);
    }

    /**
     * Checks if the account settings are known and correct.
     *
     * This is done by trying to download the contact types picklist.
     *
     * @return string
     *   Message to show in the 2nd and 3rd fieldset. Empty if successful.
     */
    protected function checkAccountSettings()
    {
        // Check if we can retrieve a picklist. This indicates if the account
        // settings are known and correct.
        $message = '';
        $this->contactTypes = null;
        $credentials = $this->acumulusConfig->getCredentials();
        if (!empty($credentials['contractcode']) && !empty($credentials['username']) && !empty($credentials['password'])) {
            $this->contactTypes = $this->service->getPicklistContactTypes();
            if (!empty($this->contactTypes['errors'])) {
                $message = $this->t($this->contactTypes['errors'][0]['code'] == 401 ? 'message_error_auth' : 'message_error_comm');
                $this->errorMessages += $this->service->resultToMessages($this->contactTypes, false);
            }
        } else {
            // First fill in your account details.
            $message = $this->t('message_auth_unknown');
        }
        return $message;
    }

    /**
     * Converts a picklist response into an options list.
     *
     * @param array $picklist
     *   The picklist result structure.
     * @param string $key
     *   The key in the picklist result structure under which the actual results
     *   can be found.
     * @param string|null $emptyValue
     *   The value to use for an empty selection.
     * @param string|null $emptyText
     *   The label to use for an empty selection.
     *
     * @return array
     */
    protected function picklistToOptions(array $picklist, $key, $emptyValue = null, $emptyText = null)
    {
        $result = array();

        if ($emptyValue !== null) {
            $result[$emptyValue] = $emptyText;
        }
        if (!empty($key)) {
            // Take the results under the key. This is to be able to follow the
            // structure returned by the picklist services.
            $picklist = $picklist[$key];
        }
        array_walk($picklist, function ($value) use (&$result) {
            list($optionValue, $optionText) = array_values($value);
            $result[$optionValue] = $optionText;
        });

        return $result;
    }

    /**
     * Returns an option list of all order statuses including an empty choice.
     *
     * @return array
     *   An options array of all order statuses.
     */
    protected function getOrderStatusesList()
    {
        $result = array();

        $result['0'] = $this->t('option_empty_triggerOrderStatus');
        $result += $this->shopCapabilities->getShopOrderStatuses();

        return $result;
    }

}
