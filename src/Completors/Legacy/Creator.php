<?php
/**
 * @noinspection DuplicatedCode  During the transition to Collectors, duplicate code will exist.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Legacy;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

use function array_key_exists;
use function count;
use function func_get_args;
use function in_array;
use function is_array;
use function is_int;

/**
 * Creates an Acumulus invoice.
 *
 * This class is based on the former Siel\Acumulus\Invoice\Creator class which
 * eventually will be replaced by code in this Collectors namespace. It contains
 * those pieces of code that have not yet been refactored to a Collector or
 * Completor.
 * - Customer, address and emailAsPdf data gathering will be fully converted to
 *   (split into) collecting and completing with the first release where any of
 *   this code is really used.
 * - Invoice and invoice lines gathering is much more difficult to split over
 *   these 2 phases so the old Creator code remains to exist for a while. But to
 *   be able to move, change or delete parts of the Creator class, while keeping
 *   code for webshops that are not yet converted running, that class was copied
 *   to here, so we don't have to touch the original file.
 *
 * @noinspection EfferentObjectCouplingInspection
 */
abstract class Creator
{
    public const VatRateSource_Exact = 'exact';
    public const VatRateSource_Exact0 = 'exact-0';
    public const VatRateSource_Calculated = 'calculated';
    public const VatRateSource_Completor = 'completor';
    public const VatRateSource_Parent = 'parent';
    public const VatRateSource_Child = 'child';
    public const VatRateSource_Strategy = 'strategy';

    public const LineType_OrderItem = 'order-item';
    public const LineType_Shipping = 'shipping';
    public const LineType_PaymentFee = 'payment';
    public const LineType_GiftWrapping = 'gift';
    public const LineType_Manual = 'manual';
    public const LineType_Discount = 'discount';
    public const LineType_Voucher = 'voucher';
    public const LineType_Other = 'other';
    public const LineType_Corrector = 'missing-amount-corrector';

    private Container $container;
    protected Config $config;
    protected Translator $translator;
    protected Source $invoiceSource;

    private FieldExpander $field;
    protected ShopCapabilities $shopCapabilities;
    protected Log $log;
    /**
     * @var Invoice
     *   Resulting Acumulus invoice.
     */
    protected Invoice $invoice;
    /**
     * The list of sources to search for properties.
     */
    protected array $propertySources;

    public function __construct(
        FieldExpander $field,
        ShopCapabilities $shopCapabilities,
        Container $container,
        Config $config,
        Translator $translator,
        Log $log
    ) {
        $this->log = $log;
        $this->field = $field;
        $this->shopCapabilities = $shopCapabilities;
        $this->container = $container;
        $this->config = $config;
        $this->translator = $translator;
        $invoiceHelperTranslations = new Translations();
        $this->translator->add($invoiceHelperTranslations);
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

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getField(): FieldExpander
    {
        return $this->field;
    }

    /**
     * Sets the source to create the invoice for.
     *
     * @param Source $invoiceSource
     */
    protected function setInvoiceSource(Source $invoiceSource): void
    {
        $this->invoiceSource = $invoiceSource;
        if (!in_array($invoiceSource->getType(), [Source::Order, Source::CreditNote], true)) {
            $this->log->error('Creator::setSource(): unknown source type %s', $this->invoiceSource->getType());
        }
    }

    /**
     * Sets the list of sources to search for a property when expanding tokens.
     */
    protected function setPropertySources(): void
    {
        // @todo: all non line related property sources can be removed (i.e. all can be removed).
        $this->propertySources = [];
        $this->propertySources['invoiceSource'] = $this->invoiceSource;
        $this->propertySources['invoiceSourceType'] = ['label' => $this->t($this->propertySources['invoiceSource']->getType())];

        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            $this->propertySources['originalInvoiceSource'] = $this->invoiceSource->getOrder();
            $this->propertySources['originalInvoiceSourceType'] =
                ['label' => $this->t($this->propertySources['originalInvoiceSource']->getType())];
        }
        $this->propertySources['source'] = $this->invoiceSource->getSource();
        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            if ($this->invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refund'] = $this->invoiceSource->getSource();
            }
            $this->propertySources['order'] = $this->invoiceSource->getOrder()->getSource();
            if ($this->invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refundedInvoiceSource'] = $this->invoiceSource->getOrder();
                $this->propertySources['refundedInvoiceSourceType'] =
                    ['label' => $this->t($this->propertySources['refundedInvoiceSource']->getType())];
                $this->propertySources['refundedOrder'] = $this->invoiceSource->getOrder()->getSource();
            }
        }
    }

    /**
     * Adds an object as property source.
     *
     * The object is added to the start of the array. So, upon token expansion,
     * it will be searched before other (already added) property sources.
     *
     * @param string $name
     *   The name to use for the source
     * @param object|array $property
     *   The source object to add.
     */
    public function addPropertySource(string $name, $property): void
    {
        $this->propertySources = [$name => $property] + $this->propertySources;
    }

    /**
     * Removes an object as property source.
     *
     * @param string $name
     *   The name of the source to remove.
     */
    public function removePropertySource(string $name): void
    {
        unset($this->propertySources[$name]);
    }

    /**
     * Creates an Acumulus invoice from an order or credit note.
     */
    public function create(Source $source, Invoice $invoice): void
    {
        $this->invoice = $invoice;
        $this->setInvoiceSource($source);
        $this->setPropertySources();
        Converter::getInvoiceLinesFromArray($this->getInvoiceLines(), $this->invoice);
    }

    /**
     * Returns the 'invoice' 'line' parts of the invoice add structure.
     *
     * @return array[]
     *   A non keyed array with all invoice lines.
     */
    protected function getInvoiceLines(): array
    {
        $itemLines = $this->getItemLines();
        $itemLines = $this->addLineType($itemLines, static::LineType_OrderItem);

        $feeLines = $this->getFeeLines();

        $discountLines = $this->getDiscountLines();
        $discountLines = $this->addLineType($discountLines, static::LineType_Discount);

        $manualLines = $this->getManualLines();
        $manualLines = $this->addLineType($manualLines, static::LineType_Manual);

        return array_merge($itemLines, $feeLines, $discountLines, $manualLines);
    }

    /**
     * Returns the item/product lines of the order.
     *
     * Override this method or implement both getItemLinesOrder() and
     * getItemLinesCreditMote().
     *
     * @return array[]
     *   An array of item line arrays.
     */
    protected function getItemLines(): array
    {
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Adds the product based tags to a line.
     *
     * The product based tags are:
     * - item number
     * - product name
     * - nature
     * - cost price
     *
     * @param array $line
     */
    protected function addProductInfo(array &$line): void
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($line, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($line, Tag::Product, $invoiceSettings['productName']);
        $this->addNature($line);
        if (!empty($invoiceSettings['costPrice'])) {
            $value = $this->getTokenizedValue($invoiceSettings['costPrice']);
            if (!empty($value) && !Number::isZero($value)) {
                // If we have a cost price we add it, even if this is no margin
                // invoice.
                $line[Tag::CostPrice] = $value;
            }
        }
    }

    /**
     * Adds the nature tag to the line.
     *
     * The nature tag indicates the nature of the article for which the line is
     * being constructed. It can be Product or Service.
     *
     * The nature can come from the:
     * - Shop settings: the nature_shop setting.
     * - Data settings: The nature field reference.
     *
     * It will be left undefined when no value can be given to it based on these
     * settings.
     *
     * @param array $line
     */
    protected function addNature(array &$line): void
    {
        if (empty($line[Tag::Nature])) {
            $shopSettings = $this->config->getShopSettings();
            switch ($shopSettings['nature_shop']) {
                case Config::Nature_Products:
                    $line[Tag::Nature] = Api::Nature_Product;
                    break;
                case Config::Nature_Services:
                    $line[Tag::Nature] = Api::Nature_Service;
                    break;
                default:
                    $invoiceSettings = $this->config->getInvoiceSettings();
                    $this->addTokenDefault($line, Tag::Nature, $invoiceSettings['nature']);
                    break;
            }
        }
    }

    /**
     * Returns all the fee lines for the order.
     *
     * Override this method if it is easier to return all fee lines at once.
     * If you do so, you are responsible for adding the line Meta::LineType
     * metadata. Otherwise, override the methods getShippingLines() (or
     * getShippingLine()), getPaymentFeeLine() (if applicable), and
     * getGiftWrappingLine() (if available).
     *
     * @return array[]
     *   A, possibly empty, array of fee line arrays.
     */
    protected function getFeeLines(): array
    {
        $result = [];

        $shippingLines = $this->getShippingLines();
        if ($shippingLines) {
            $shippingLines = $this->addLineType($shippingLines, static::LineType_Shipping);
            $result = array_merge($result, $shippingLines);
        }

        $line = $this->getPaymentFeeLine();
        if ($line) {
            $line = $this->addLineType($line,static::LineType_PaymentFee);
            $result[] = $line;
        }

        $line = $this->getGiftWrappingLine();
        if ($line) {
            $line = $this->addLineType($line,static::LineType_GiftWrapping);
            $result[] = $line;
        }

        return $result;
    }

    /**
     * Returns the shipping costs lines.
     *
     * This default implementation assumes there will be at most one shipping
     * line and as such calls the getShippingLine() method.
     *
     * Override if the shop allows for multiple shipping lines.
     *
     * @return array[]
     *   A, possibly empty, array of shipping line arrays.
     */
    protected function getShippingLines(): array
    {
        $result = [];
        $line = $this->getShippingLine();
        if ($line) {
            $result[] = $line;
        }
        return $result;
    }

    /**
     * Returns the shipping costs line.
     *
     * To be able to produce a packing slip, a shipping line should normally be
     * added, even for free shipping.
     *
     * @return array
     *   A line array, empty if there is no shipping fee line.
     */
    abstract protected function getShippingLine(): array;

    /**
     * Returns the shipment method name.
     *
     * This method should be overridden by web shops to provide a more detailed
     * name of the shipping method used.
     *
     * This base implementation returns the translated "Shipping costs" string.
     *
     * @return string
     *   The name of the shipping method used for the current order.
     */
    protected function getShippingMethodName(): string
    {
        return $this->t('shipping_costs');
    }

    /**
     * Returns the payment fee line.
     *
     * This base implementation returns an empty array: no payment fee line.
     *
     * @return array
     *   A line array, empty if there is no payment fee line.
     */
    protected function getPaymentFeeLine(): array
    {
        return [];
    }

    /**
     * Returns the gift wrapping costs line.
     *
     * This base implementation return an empty array: no gift wrapping.
     *
     * @return array
     *   A line array, empty if there is no gift wrapping fee line.
     */
    protected function getGiftWrappingLine(): array
    {
        return [];
    }

    /**
     * Returns any applied discounts and partial payments (gift vouchers).
     *
     * Override this method or implement both getDiscountLinesOrder() and
     * getDiscountLinesCreditNote().
     *
     * Notes:
     * - In all cases you have to return an array of line arrays, even if your
     *   shop only allows 1 discount per order or stores all discount
     *   information as 1 total, and you can only return 1 line.
     * - if your shop already divided the discount amount over the eligible
     *   products, it is better to still return a separate discount line
     *   describing the discount code applied and the discount amount, but
     *   with a 0 amount tag. This allows e.g. to explain the lower than
     *   expected product prices on the item lines and/or the free shipping
     *   line.
     *
     * @return array[]
     *   A, possibly empty, array of discount line arrays.
     */
    protected function getDiscountLines(): array
    {
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Returns any manual lines.
     *
     * Manual lines may appear on credit notes to overrule amounts as calculated
     * by the system. E.g. discounts applied on items should be taken into
     * account when refunding (while the system did not or does not know if the
     * discount also applied to that product), shipping costs may be returned
     * except for the handling costs, etc.
     *
     * @return array[]
     *   A, possibly empty, array of manual line arrays.
     */
    protected function getManualLines(): array
    {
        return [];
    }

    /**
     * Returns whether the margin scheme may be used.
     *
     * @return bool
     *
     * @todo: remove margin scheme handling from (plugin specific) creators and
     *   move it to the completor phase. This will aid in simplifying the
     *   creators towards raw data collectors.
     */
    protected function allowMarginScheme(): bool
    {
        $shopSettings = $this->config->getShopSettings();
        return $shopSettings['marginProducts'] !== Config::MarginProducts_No;
    }

    /**
     * Helper method to add a non-empty possibly tokenized value to an array.
     * This method will not overwrite existing values.
     *
     * @param array $array
     * @param string $key
     * @param string $token
     *   String value that may contain token definitions.
     *
     * @return bool
     *   Whether the default was added.
     */
    protected function addTokenDefault(array &$array, string $key, string $token): bool
    {
        if (empty($array[$key]) && !empty($token)) {
            $value = $this->getTokenizedValue($token);
            if (!empty($value)) {
                $array[$key] = $value;
                return true;
            }
        }
        return false;
    }

    /**
     * Wrapper method around Token::expand().
     *
     * @param string $pattern
     *
     * @return mixed
     *   The pattern with fields expanded with their actual value. If the $pattern
     *   contains exactly 1 variable field specification, i.e. it begins with a '[' and
     *   the first and only ']' is at the end, the type of the returned value is that of
     *   the property referred to, otherwise it is a string or null if not found.
     */
    protected function getTokenizedValue(string $pattern)
    {
        return $this->getField()->expand($pattern, $this->propertySources);
    }

    /**
     * Helper method to add a default non-empty value to an array.
     * This method will not overwrite existing values.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *   Whether the default was added.
     */
    protected function addDefault(array &$array, string $key, $value): bool
    {
        if (empty($array[$key]) && !empty($value)) {
            $array[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * Helper method to add a warning to an array.
     * Warnings are placed in the $array under the key Meta::Warning. If no
     * warning is set, $warning is added as a string, otherwise it becomes an
     * array of warnings to which this $warning is added.
     */
    protected function addWarning(array &$array, string $warning, string $severity = Meta::Warning): void
    {
        if (!isset($array[$severity])) {
            $array[$severity] = $warning;
        } else {
            if (!is_array($array[$severity])) {
                $array[$severity] = (array) $array[$severity];
            }
            $array[$severity][] = $warning;
        }
    }

    /**
     * Adds a meta-line-type tag to the line(s) and its children, if any.
     *
     * @param array|array[] $lines
     *   This may be a single line not placed in an array.
     * @param string $lineType
     *   The line type to add to the line.
     *
     * @return array|array[]
     *   The line(s) with the line type meta tag added.
     */
    protected function addLineType(array $lines, string $lineType): array
    {
        if (count($lines) !== 0) {
            // reset(), so key() does not return null if the array is not empty.
            reset($lines);
            if (is_int(key($lines))) {
                // Numeric index: array of lines.
                foreach ($lines as &$line) {
                    $line = $this->addLineType($line, $lineType);
                }
            } else {
                // String key: single line.
                $this->addDefault($lines, Meta::LineType, $lineType);
                if (isset($lines[Meta::ChildrenLines])) {
                    $lines[Meta::ChildrenLines] = $this->addLineType($lines[Meta::ChildrenLines], $lineType);
                }
            }
        }
        return $lines;
    }

    /**
     * Returns the range in which the vat rate will lie.
     * If a web shop does not store the vat rates used in the order, we must
     * calculate them using a (product) price and the vat on it. But as web
     * shops often store these numbers rounded to cents, the vat rate
     * calculation becomes imprecise. Therefore, we compute the range in which
     * it will lie and will let the Completor do a comparison with the actual
     * vat rates that an order can have (one of the Dutch or, for electronic
     * services, other EU country VAT rates).
     * - If $denominator = 0 (free product), the vat rate will be set to null
     *   and the Completor will try to get this line listed under the correct
     *   vat rate.
     * - If $numerator = 0 the vat rate will be set to 0 and be treated as if it
     *   is an exact vat rate, not a vat range.
     *
     * @param float $numerator
          *   The amount of VAT as received from the web shop.
     * @param float $denominator
     *   The price of a product excluding VAT as received from the web shop.
     * @param float $numeratorPrecision
     *   The precision used when rounding the number. This means that the
     *   original numerator will not differ more than half of this.
     * @param float $denominatorPrecision
     *   The precision used when rounding the number. This means that the
     *   original denominator will not differ more than half of this.
     *
     * @return array
     *   Array with keys (not all keys will always be available):
     *   - 'vatrate'
     *   - 'vatamount'
     *   - 'meta-vatrate-min'
     *   - 'meta-vatrate-max'
     *   - 'meta-vatamount-precision'
     *   - 'meta-vatrate-source'
     * @todo: can we move this from the (plugin specific) creators to the
     *   completor phase? This would aid in simplifying the creators towards raw
     *   data collectors.
     */
    public static function getVatRangeTags(
        float $numerator,
        float $denominator,
        float $numeratorPrecision = 0.01,
        float $denominatorPrecision = 0.01
    ): array {
        if (Number::isZero($denominator, 0.0001)) {
            $result = [
                Tag::VatRate => null,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => static::VatRateSource_Completor,
            ];
        } elseif (Number::isZero($numerator, 0.0001)) {
            $result = [
                Tag::VatRate => 0,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => static::VatRateSource_Exact0,
            ];
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision);
            $result = [
                Tag::VatRate => 100.0 * $range['calculated'],
                Meta::VatRateMin => 100.0 * $range['min'],
                Meta::VatRateMax => 100.0 * $range['max'],
                Meta::VatAmount => $numerator,
                Meta::PrecisionUnitPrice => $denominatorPrecision,
                Meta::PrecisionVatAmount => $numeratorPrecision,
                Meta::VatRateSource => static::VatRateSource_Calculated,
            ];
        }
        return $result;
    }

    /**
     * Calls a method constructed of the method name and the source type.
     * If the implementation/override of a method depends on the type of invoice
     * source it might be better to implement 1 method per source type. This
     * method calls such a method assuming it is named {method}{source-type}.
     * Example: if getLineItem($line) would be very different for an order
     * versus a credit note: do not override the base method but implement 2 new
     * methods getLineItemOrder($line) and getLineItemCreditNote($line).
     *
     * @param string $method
     *   The name of the base method for which to call the Source type specific
     *   variant.
     * @param array $args
     *   The arguments to pass to the method to call.
     *
     * @return mixed
     *   The return value of that method call, or null if the method does not
     *   exist.
     */
    protected function callSourceTypeSpecificMethod(string $method, array $args = [])
    {
        $method .= $this->invoiceSource->getType();
        if (method_exists($this, $method)) {
            return $this->$method(... $args);
        }
        return null;
    }
}
