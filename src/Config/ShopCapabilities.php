<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

use InvalidArgumentException;
use RuntimeException;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Source;

use function in_array;

/**
 * Defines an interface to access the shop specific's capabilities.
 */
abstract class ShopCapabilities
{
    protected Translator $translator;
    protected string $shopName;

    public function __construct(string $shopNamespace, Translator $translator)
    {
        $this->translator = $translator;
        $pos = strrpos($shopNamespace, '\\');
        $this->shopName = $pos !== false ? substr($shopNamespace, $pos + 1) : $shopNamespace;
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
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    /**
     * Returns an array with shop specific configuration defaults.
     *
     * Any key defined in {@see Config::getKeyInfo()} that can be given a more logical
     * value, given that the library is running in a specific webshop, should be returned
     * here.
     *
     * With most keys susceptible to have a shop specific default now moved to the
     * mappings, this base method, which was abstract before, now returns an empty array.
     */
    public function getDefaultShopConfig(): array
    {
        return [];
    }

    /**
     * Returns an array with shop specific property mapping defaults.
     *
     * See the {@see \Siel\Acumulus\Data\_Documentation Data namespace} to get all the
     * possible {@see \Siel\Acumulus\Data\AcumulusObject} types and their
     * {@see \Siel\Acumulus\Data\AcumulusProperty} properties.
     *
     * See {@see \Siel\Acumulus\Helpers\FieldExpander} or the help text under key
     * 'desc_tokens' in siel/acumulus/src/Shop/ConfigFormTranslations.php for more info
     * about the possible options to define combinations or a selection of various tokens.
     *
     * This base method is abstract, because shop mappings are per definition shop
     * dependent...
     *
     * The value returned is used by {@see \Siel\Acumulus\Config\Mappings::getDefaults()}
     * to add to and override shop independent defaults.
     *
     * @return string[][]
     *   A keyed array of keyed arrays. At the 1st level the keys are the
     *   Data\...Type::... constants and the values are the mappings for that object type.
     *   A mapping being a keyed array, with as keys the property names and as values a
     *   so-called field expansion specification, see
     *   {@see \Siel\Acumulus\Helpers\FieldExpander}.
     */
    abstract public function getDefaultShopMappings(): array;

    /**
     * Returns an option list of all shop order statuses.
     *
     * Note that the IDs are the values that are stored in the config and are
     * later on compared with the order status when a web shop event occurs
     * that may lead to sending the invoice to Acumulus.
     *
     * @return string[]
     *   An array of all shop order statuses, with the key being the ID for
     *   the dropdown item and the value being the label for the dropdown item.
     */
    abstract public function getShopOrderStatuses(): array;

    /**
     * Returns a list of invoice source types supported by this shop.
     *
     * The default implementation returns order and credit note. Override if the
     * specific shop does not support credit notes (or supports other types).
     *
     * @return string[]
     *   The list of supported invoice source types. The keys are the internal
     *   {@see \Siel\Acumulus\Invoice\Source} constants, the values are
     *   translated labels.
     */
    public function getSupportedInvoiceSourceTypes(): array
    {
        return [
            Source::Order => ucfirst($this->t(Source::Order)),
            Source::CreditNote => ucfirst($this->t(Source::CreditNote)),
        ];
    }

    /**
     * Returns an option list of all shop invoice related events.
     *
     * This list represents the shop initiated events that may trigger the sending of the
     * invoice to Acumulus.
     *
     * @return string[]
     *   An array of all shop invoice related events, with the key being the ID for the
     *   dropdown item, 1 of the {@see \Siel\Acumulus\Config}::TriggerInvoiceEvent_...
     *   constants, and the value being the (translated) label for the dropdown item.
     */
    public function getTriggerInvoiceEventOptions(): array
    {
        return [
            Config::TriggerInvoiceEvent_None => $this->t('option_triggerInvoiceEvent_0'),
        ];
    }

    /**
     * Returns an option list of credit note related events.
     *
     * This list represents the shop initiated events that may trigger the
     * sending of a credit invoice to Acumulus.
     *
     * This default implementation returns
     * - PluginConfig::TriggerCreditNoteEvent_None for all shops, and as only
     *   value for shops that do not support credit notes
     * - PluginConfig::TriggerCreditNoteEvent_Create for shops that do support
     *   credit notes (based on {@see getSupportedInvoiceSourceTypes()}).
     *
     * @return string[]
     *   An array of all credit note related events, with the key being the ID
     *   for the dropdown item, 1 of the {@see \Siel\Acumulus\Config}
     *   TriggerCreditNoteEvent_... constants, and the value being the translated label
     *   for the dropdown item.
     *
     * @noinspection PhpUnused Called via method name construction in
     *   BaseConfigForm::getOptionsOrHiddenField().
     */
    public function getTriggerCreditNoteEventOptions(): array
    {
        $result = [
            Config::TriggerCreditNoteEvent_None => $this->t('option_triggerCreditNoteEvent_0'),
        ];

        if (in_array(Source::CreditNote, $this->getSupportedInvoiceSourceTypes())) {
            $result[Config::TriggerCreditNoteEvent_Create] = $this->t('option_triggerCreditNoteEvent_1');
        }

        return $result;
    }

    /**
     * Returns a list of valid sources that can be used as invoice number.
     *
     * This may differ per shop as not all shops support invoices as a separate
     * entity.
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation, but including at least
     * {@see Config::InvoiceNrSource_Acumulus}.
     *
     * @return string[]
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    public function getInvoiceNrSourceOptions(): array
    {
        return [
            Config::InvoiceNrSource_ShopInvoice => $this->t('option_invoiceNrSource_1'),
            Config::InvoiceNrSource_ShopOrder => $this->t('option_invoiceNrSource_2'),
            Config::InvoiceNrSource_Acumulus => $this->t('option_invoiceNrSource_3'),
        ];
    }

    /**
     * Returns a list of valid date sources that can be used as invoice date.
     *
     * This may differ per shop as not all shops support invoices as a separate
     * entity.
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation, but including at least
     * {@see Config::IssueDateSource_Transfer}.
     *
     * @return string[]
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    public function getDateToUseOptions(): array
    {
        return [
            Config::IssueDateSource_InvoiceCreate => $this->t('option_dateToUse_1'),
            Config::IssueDateSource_OrderCreate => $this->t('option_dateToUse_2'),
            Config::IssueDateSource_Transfer => $this->t('option_dateToUse_3'),
        ];
    }

    /**
     * Returns an option list of active payment methods.
     *
     * The ids returned are later on used to compare with an order's payment
     * method, so the appropriate template and account can be chosen.
     *
     * @return string[]
     *   An array of active payment methods, with the key being the id (internal
     *   name) for the dropdown item and the value being the label for the
     *   dropdown item.
     */
    abstract public function getPaymentMethods(): array;

    /**
     * Returns an option list of tax classes.
     *
     * @return string[]
     *   An array of tax classes, with the key being the tax class id, to be
     *   used as id for the dropdown item and the value being the tax class
     *   name, to be used as the label for the dropdown item.
     */
    abstract public function getVatClasses(): array;

    /**
     * Returns whether our module for this shop implements the stock management features.
     */
    public function hasStockManagement(): bool
    {
        return false;
    }

    /**
     * Returns an option list of fields that can be used to match with Acumulus products.
     *
     * This basis implementation returns an empty array for shops that do not (yet)
     * implement stock management and throws a runtime exception for those that do,
     * but have not overridden this method. Thus: must be overridden by shops that do
     * implement stock management.
     *
     * @return string[]
     *   An array of product fields, with the key being a mapping,
     *   and the value being the label of that field in Product forms or lists.
     */
    public function getProductMatchShopFields(): array
    {
        if (!$this->hasStockManagement()) {
            return [];
        }
        throw new RuntimeException(__METHOD__ . ' is not implemented');
    }

    /**
     * Returns a link to a form page or an image.
     *
     * If the web shop adds a session token or something like that to
     * administrative links, the returned link should contain so as well.
     *
     * @param string $linkType
     *   The form or resource to get the link to: 'settings', 'mappings', 'batch',
     *   'activate', 'register', 'logo', 'pro-support-link', 'pro-support-img'.
     * @param mixed|null $parameter
     *   Any parameter to add to the link. Used to get a product specific page (thus,
     *   $parameter being a product id).
     */
    public function getLink(string $linkType, mixed $parameter = null): string
    {
        if (in_array($linkType, ['fiscal-address-setting', 'product'])) {
            return '#';
        }
        throw new InvalidArgumentException(__METHOD__ . "('$linkType'): unknown link type");
    }

    /**
     * Returns whether our module for this shop implements the InvoiceStatus screen.
     *
     * At this moment all shops implements this screen, so this method returns
     * true and is not overridden.
     *
     * @return true
     */
    public function hasInvoiceStatusScreen(): bool
    {
        return true;
    }

    /**
     * Returns whether our module for this shop implements the features on the Order list
     * screen.
     */
    public function hasOrderList(): bool
    {
        return false;
    }

    /**
     * Returns the name of the setting or the address type that the shop uses for fiscal
     * calculations.
     *
     * @return string
     *   One of the {@see \Siel\Acumulus\Data\AddressType} constants if the shop does not
     *   allow to choose the address, the name of the setting that determines which
     *   address is used otherwise.
     */
    abstract public function getFiscalAddressSetting(): string;
}
