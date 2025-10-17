<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Invoice;

use Mollie\Factory\ModuleFactory;
use Mollie\Repository\MolOrderPaymentFeeRepositoryInterface;
use MolOrderPaymentFee;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Meta;

/**
 * ThirdPartyModuleSupport encapsulates support for 3rd party modules.
 *
 * Currently, we support:
 * - Payment fees from the Mollie module.
 * - Payment fees from the "Paypal with a fee" module.
 */
class ThirdPartyModuleSupport
{

    public function __construct(private readonly Source $source)
    {
    }

    /**
     * Returns the set of payment fee lines infos.
     *
     * In PrestaShop, we've chosen to use a minimally filled {@see Line} as a payment fee
     * line info. Minimally filled means that only properties that are avaialable in the
     * database are added, default and calculated properties are not set.
     *
     * @return Line[]
     *   Description.
     *
     * @throws \PrestaShopException
     */
    public function getPaymentFeeLineInfos(): array
    {
        return $this->addMolliePaymentFeeLineInfos($this->addPaypalWithAFeeLineInfos([]));
    }

    /**
     * If set, adds a line for a Mollie payment fee.
     *
     * @param Line[] $lineInfos
     *
     * @return Line[]
     *
     * @throws \PrestaShopException
     */
    private function addMolliePaymentFeeLineInfos(array $lineInfos): array
    {
        if ($this->source->getType() === Source::Order && class_exists(ModuleFactory::class)) {
            $paymentFeeRepository = (new ModuleFactory())->getModule()?->getService(MolOrderPaymentFeeRepositoryInterface::class);
            if ($paymentFeeRepository instanceof MolOrderPaymentFeeRepositoryInterface) {
                /** @noinspection PhpUndefinedMethodInspection false positive (why?) */
                $paymentFee = $paymentFeeRepository->findOneBy(['id_order' => $this->source->getId()]);
                if ($paymentFee instanceof MolOrderPaymentFee) {
                    $sign = $this->source->getSign();
                    $line = new Line();
                    $line->setType(LineType::PaymentFee);
                    $line->unitPrice = $sign * $paymentFee->fee_tax_excl;
                    $line->metadataSet(Meta::UnitPriceInc, $sign * $paymentFee->fee_tax_incl);
                    $lineInfos[] = $line;
                }
            }
        }
        return $lineInfos;
    }

    /**
     * If set, adds a line for a Paypal with a Fee payment fee.
     *
     * @param Line[] $lineInfos
     *
     * @return Line[]
     */
    private function addPaypalWithAFeeLineInfos(array $lineInfos): array
    {
        if ($this->source->getType() === Source::Order) {
            /** @var \Order $order */
            $order = $this->source->getShopObject();
            /**
             * @noinspection MissingIssetImplementationInspection These fields are set by
             *   the module "PayPal with a fee".
             */
            if (isset($order->payment_fee, $order->payment_fee_rate) && (float) $order->payment_fee !== 0.0) {
                $sign = $this->source->getSign();
                $line = new Line();
                $line->setType(LineType::PaymentFee);
                $line->unitPrice = $sign * $order->payment_fee;
                $line->vatRate = $order->payment_fee_rate;
                $lineInfos[] = $line;
            }
        }
        return $lineInfos;
    }
}
