<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\BasicSubmit;
use Siel\Acumulus\Data\Connector;
use Siel\Acumulus\Data\Contract;
use Siel\Acumulus\Data\DataType;

/**
 * Collects basic submit data from the shop.
 *
 * The values needed to construct the basic submit data, including its subclasses
 * {@see Contract} and {@see Connector}, are all wrapped in our library classes
 * {@see \Siel\Acumulus\Config\Environment} and {@see \Siel\Acumulus\Config\Config}.
 * All fields can be mapped from these objects.
 *
 * Note that even though most fields do not really depend on shop data, we use mappings
 * for all fields anyway because these mappings are all straightforward without any logic.
 */
class BasicSubmitCollector extends Collector
{
    /**
     * This override collects the fields of a {@see \Siel\Acumulus\Data\BasicSubmit}
     * object, as well as of its 2 child classes {@see Contract} md {@see Connector}.
     */
    public function collect(PropertySources $propertySources, ?array $fieldSpecifications): BasicSubmit
    {
        /** @var BasicSubmit $basicSubmit */
        $basicSubmit = parent::collect($propertySources, $fieldSpecifications);
        $basicSubmit->setContract($this->collectContract($propertySources));
        $basicSubmit->setConnector($this->collectConnector($propertySources));
        return $basicSubmit;
    }

    public function collectContract(PropertySources $propertySources): Contract
    {
        /** @var \Siel\Acumulus\Data\Contract $contract */
        $contract = $this->getContainer()->getCollector(DataType::Contract)->collect($propertySources, null);
        return $contract;
    }

    public function collectConnector(PropertySources $propertySources): Connector
    {
        /** @var \Siel\Acumulus\Data\Connector $connector */
        $connector = $this->getContainer()->getCollector(DataType::Connector)->collect($propertySources, null);
        return $connector;
    }

    /**
     * @param \Siel\Acumulus\Data\BasicSubmit $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $acumulusObject->testMode = $this->isTestMode() ? Api::TestMode_Test : Api::TestMode_Normal;
    }

    /**
     * Indicates if we are in test mode.
     *
     * @return bool
     *   True if we are in test mode, false otherwise.
     */
    protected function isTestMode(): bool
    {
        return $this->getContainer()->getConfig()->get('debug') === Config::Send_TestMode;
    }
}
