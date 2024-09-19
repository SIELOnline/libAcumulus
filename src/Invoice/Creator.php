<?php
/**
 * @noinspection DuplicatedCode  During the transition to Collectors, duplicate code will exist.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;

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
 */
abstract class Creator
{
    private Container $container;
    protected Mappings $mappings;
    protected Config $config;
    protected Translator $translator;
    protected Source $invoiceSource;

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
        ShopCapabilities $shopCapabilities,
        Container $container,
        Mappings $mappings,
        Config $config,
        Translator $translator,
        Log $log
    ) {
        $this->log = $log;
        $this->shopCapabilities = $shopCapabilities;
        $this->container = $container;
        $this->mappings = $mappings;
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
        //    Is this true? Retrieving tax class/rate metadata, etc?
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
        $feeLines = $this->getFeeLines();

        $discountLines = $this->getDiscountLines();
        $discountLines = $this->addLineType($discountLines, LineType::Discount);

        $manualLines = $this->getManualLines();
        $manualLines = $this->addLineType($manualLines, LineType::Manual);

        return array_merge($feeLines, $discountLines, $manualLines);
    }

    /**
     * Returns all the fee lines for the order.
     *
     * Override this method if it is easier to return all fee lines at once.
     * If you do so, you are responsible for adding the line Meta::SubType
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

        $line = $this->getPaymentFeeLine();
        if ($line) {
            $line = $this->addLineType($line, LineType::PaymentFee);
            $result[] = $line;
        }

        $line = $this->getGiftWrappingLine();
        if ($line) {
            $line = $this->addLineType($line, LineType::GiftWrapping);
            $result[] = $line;
        }

        return $result;
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
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args()) ?? [];
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
     * Adds a meta-sub-type tag to the line(s) and its children, if any.
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
                $lines[Meta::SubType] = $lineType;
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
                Fld::VatRate => null,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => VatRateSource::Completor,
            ];
        } elseif (Number::isZero($numerator, 0.0001)) {
            $result = [
                Fld::VatRate => 0,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => VatRateSource::Exact0,
            ];
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision);
            $result = [
                Fld::VatRate => 100.0 * $range['calculated'],
                Meta::VatRateMin => 100.0 * $range['min'],
                Meta::VatRateMax => 100.0 * $range['max'],
                Meta::VatAmount => $numerator,
                Meta::PrecisionUnitPrice => $denominatorPrecision,
                Meta::PrecisionVatAmount => $numeratorPrecision,
                Meta::VatRateSource => VatRateSource::Calculated,
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
    protected function callSourceTypeSpecificMethod(string $method, array $args = []): mixed
    {
        $method .= $this->invoiceSource->getType();
        if (method_exists($this, $method)) {
            return $this->$method(... $args);
        }
        return null;
    }
}
