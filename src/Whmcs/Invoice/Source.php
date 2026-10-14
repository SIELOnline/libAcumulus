<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 * @noinspection SpellCheckingInspection Too many compound words in (all lowercase) array keys.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Invoice;

use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Whmcs\Helpers\LocalApiTrait;

use function is_array;
use function is_object;
use function strlen;

/**
 * Wraps a WHMCS invoice in an invoice source object.
 *
 * For now, we only accept Invoices. In WHMCS, invoices are fully detailed data objects
 * not mere PDF's. Orders are so as well, but orders do not store tax and will (always?)
 * refer to an invoice anyway. Whereas, an invoice does not refer to an Order, probably
 * because of recurring invoices/payments due to automatic renewals which only will add an
 * invoice, not an order.
 *
 * WHMCS uses the (Laravel) Illuminate library for db-access. This library returns
 * stdClass objects instead of arrays as the local API does. Moreover, it also doesn't
 * contain the items. So when a stdClass is passed to the constructor we extract the id
 * and pass that to the localApi to get thinvocie as an array with its items.
 *
 * @todo: WHMCS 9 supports credit notes, but support is yet to be added.
 * @todo: Check for the following methods if WHMCS can have these and, if so, how to
 *   recognise and retrieve them:
 *   - getShippingLineInfos() (probably not ...)
 *   - getGiftWrappingFeeLineInfos()
 *   - getPaymentFeeLineInfos()
 *   - getOtherLineInfos()
 *   - getDiscountLineInfos()
 *   - getManualLineInfos()
 *   - getVoucherLineInfos()
 *
 * @method array getShopObject()
 * @method array getInvoice()
 */
class Source extends BaseSource
{
    use LocalApiTrait;

    public function __construct(string $type, object|array|int|string|null $idOrObject, Container $container)
    {
        if (is_object($idOrObject)) {
            $idOrObject = $this->localApi()->getInvoice($idOrObject->id);
        }
        parent::__construct($type, $idOrObject, $container);
    }

    /**
     * Loads an invoice for the set id.
     *
     * @throws  \RuntimeException
     *   If source type is an invalid type or the id does not point to a valid source.
     */
    protected function setShopObject(): void
    {
        $this->shopObject = $this->localApi()->getInvoice($this->id);
    }

    /**
     * Sets the id based on the loaded invoice.
     *
     * @throws \RuntimeException
     *   If $idOrSource is empty or not a valid source.
     */
    protected function setId(): void
    {
        if (!is_array($this->shopObject)) {
            $type = get_debug_type($this->shopObject);
            throw new RuntimeException("'$type' is not an array");
        }
        if (!isset($this->shopObject['invoiceid'])) {
            throw new RuntimeException('Shop object does not have an invoice id');
        }
        $this->id = $this->shopObject['invoiceid'];
    }

    /**
     * Returns the user facing reference for the web shop's invoice source.
     *
     * @return string
     *   The user facing reference for the web shop's invoice source.
     *   This is not necessarily the internal id.
     */
    public function getReference(): string
    {
        return $this->getShopObject()['invoicenum'] ?: (string) $this->getId();
    }

    public function getDate(): string
    {
        return substr((string) $this->shopObject['date'], 0, strlen('2000-01-01'));
    }

    public function getStatus(): ?string
    {
        return $this->getShopObject()['status'] ?: null;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->getShopObject()['paymentmethod'] ?: null;
    }

    /**
     * {@inheritdoc}
     *
     * In WHMCS, the invoice's status is the payment status: 'Paid" or 'Unpaid'. The
     * payment status is stored in the, optional, originating order as 'paymentstatus'.
     */
    public function getPaymentStatus(): int
    {
        return ($this->getShopObject()['status'] ?? null) === 'Paid' ? Api::PaymentStatus_Paid : Api::PaymentStatus_Due;
    }

    /**
     * Returns the payment date of the invoice source.
     *
     * @return string|null
     *   The payment date of the invoice source (yyyy-mm-dd).
     */
    public function getPaymentDate(): ?string
    {
        if ($this->getPaymentStatus() === Api::PaymentStatus_Due) {
            return null;
        }
        return !empty($this->getInvoice()['datepaid']) ? substr($this->getInvoice()['datepaid'], 0, strlen('2000-01-01')) : null;
    }

    public function getCountryCode(): string
    {
        return $this->getClient()->getShopObject()->getCountryNameAttribute();
    }

    /**
     * {@inheritdoc}
     *
     * For WHMCS, only invoices are supported as sources, thus the invoice id
     * is the source id.
     */
    public function getInvoiceId(): int
    {
        return $this->getId();
    }

    /**
     * {@inheritdoc}
     *
     * For WHMCS, only invoices are supported as sources, thus the invoice reference
     * is the source reference.
     */
    public function getInvoiceReference(): string
    {
        return $this->getReference();
    }

    /**
     * {@inheritdoc}
     *
     * For WHMCS, only invoices are supported as sources, thus the invoice reference
     * is the source reference.
     */
    public function getInvoiceDate(): string
    {
        return $this->getDate();
    }

    /**
     * @todo: non Euro currencies: as soon as we have a test order with that.
     */
    public function getCurrency(): Currency
    {
//        $currencyCode = $this->getShopObject()['currencysuffix'] ?? 'EUR';
//        if ($currencyCode !== 'EUR') {
//            $currency = $this->localApi()->getCurrencyBySuffix($currencyCode);
//            $result = new Currency($currency['code'], $currency['rate'], true);
//        } else {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @noinspection OneTimeUseVariablesInspection */
        $result = new Currency('EUR');
//        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * I doubt that the field 'tax2' is ever filled in the Dutch situation, but add it
     * anyway, even if it is always 0.0. The same holds for the field 'credit', but as
     * that is not a tax, perhaps 'subtotal' should not be passed as price ex at all.
     *
     * @todo: find out what the field 'credit' contains.
     */
    public function getTotals(): Totals
    {
        return new Totals(
            (float) $this->getShopObject()['total'],
            (float) $this->getShopObject()['tax'] + (float) $this->getShopObject()['tax2'],
            (float) $this->getShopObject()['subtotal']
        );
    }

    protected function setInvoice(): void
    {
        $this->invoice = $this->getShopObject();
    }

    /**
     * This WHMCS override wraps invoice items in Items,
     * ignoring empty lines, that is, lines with 0 quantity and total ("comment" lines?).
     *
     * @return array[]
     */
    protected function createItems(): array
    {
        $result = [];

        /** @var array[] $items */
        $items = $this->getShopObject()['items']['item'] ?? [];
        foreach ($items as $item) {
            // Only add when this is not an empty line.
            // @todo: does WHMCS have other items? How to recognise? Only add when a product/service item.
            if (!Number::isZero((float) $item['amount'])) {
                $result[] = $this->getContainer()->createItem($item, $this);
            }
        }

        return $result;
    }

    protected function createClient(): ?Client
    {
        return $this->getContainer()->createClient($this->getShopObject()['userid'], $this);
    }
}
