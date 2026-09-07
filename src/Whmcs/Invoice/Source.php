<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 * @noinspection SpellCheckingInspection Too many compound words in (all lowercase) array keys.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Invoice;

use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Whmcs\Helpers\LocalApiTrait;

use function is_array;
use function sprintf;
use function strlen;

/**
 * Wraps a WHMCS invoice in an invoice source object.
 *
 * For now, we only accept Invoices. In WHMCS, invoices are fully detailed data objects
 * not mere PDF's. Orders are so as well, but orders do not store tax and will (always?)
 * refer to an invoice anyway. Whereas, an invoice does not refer to an Order, probably
 * because of recurring payments due to automatic renewals which only will add an invoice,
 * not an order.
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
 *   - etVoucherLineInfos()
 *
 * @method array getShopObject()
 */
class Source extends BaseSource
{
    use LocalApiTrait;

    // @todo: move property to Shop namespace so all shops can use it.
    protected ?Client $client = null;

    /**
     * Loads an invoice for the set id.
     *
     * @throws  \RuntimeException
     *   If source type is an invalid type or the id does not point to a valid source.
     */
    protected function setShopObject(): void
    {
        if ($this->type === Source::Invoice) {
            $this->shopObject = $this->localApi()->getInvoice($this->id);
        } else {
            throw new RuntimeException(sprintf('Not (yet) a supported source type (%s)', $this->type));
        }
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
        if (!isset($this->shopObject['id'])) {
            throw new RuntimeException('Shop object does not an id');
        }
        $this->id = $this->shopObject['id'];
    }

    /**
     * Returns the user facing reference for the web shop's invoice source.
     *
     * @return int
     *   The user facing id for the web shop's invoice source.
     *   This is not necessarily the internal id.
     */
    public function getReference(): int
    {
        if ($this->getType() === Source::Invoice) {
            return !empty($this->getShopObject()['invoicenum']) ? $this->getShopObject()['invoicenum'] : $this->getId();
        }
        return parent::getReference();
    }

    protected function createClient(): ?Client
    {
        // @todo: move code to Container and method to Shop namespace so all shops can use it.
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getContainer()->getInstance('Client', 'Shop', [$this->getShopObject()['userid']], true);
    }

    public function getClient(): Client
    {
        // @todo: move method to Shop namespace so all shops can use it.
        if (!isset($this->client)) {
            $this->client = $this->createClient();
        }
        return $this->client;
    }

    public function getDate(): string
    {
        return substr((string) $this->shopObject['date'], 0, strlen('2000-01-01'));
    }

    public function getStatus(): ?string
    {
        return !empty($this->getShopObject()['status']) ? $this->getShopObject()['status'] : null;
    }

    public function getPaymentMethod(): ?string
    {
        return !empty($this->getShopObject()['paymentmethod']) ? $this->getShopObject()['paymentmethod'] : null;
    }

    /**
     * {@inheritdoc}
     *
     * In WHMCS, the invoice's status is the payment statuses: 'Paid" or 'Unpaid'. The
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
        $contact = $this->localApi()->getClient($this->getShopObject()['userid']);
        return $contact['country'];
    }

    /**
     * @todo: I have no idea if currency suffix is always filled and is unique. If not,
     *   we must use the amounts in the invoice (that do not have a currency! => default
     *   configured currency?) and always return 'EUR' here.
     */
    public function getCurrency(): Currency
    {
        $currencyCode = $this->getShopObject()['currencysuffix'] ?? 'EUR';
        if ($currencyCode !== 'EUR') {
            $currency = $this->localApi()->getCurrencyBySuffix($currencyCode);
            $result = new Currency($currency['code'], $currency['rate'], true);
        } else {
            $result = new Currency('EUR');
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * I doubt that the field 'tax2' is ever filled in the Dutch situation, but add it
     * anyway, even if it is always 0.0. The same holds for the field 'credit', but as
     * that is not a tax, perhaps 'subtotal' should not be passed as price ex at all.
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
        if ($this->getType() === Source::Order && !empty($this->getShopObject()['invoiceid'])) {
            $this->invoice = $this->localApi()->getInvoice($this->getShopObject()['invoiceid']);
        }
    }

    /**
     * This WooCommerce override wraps {@see \WC_Order_Item_Product}s in Items,
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
}
