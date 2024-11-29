<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;

/**
 * Represents the {@link https://www.siel.nl/acumulus/API/Basic_Submit/ basic submit}
 * part of an Acumulus API request.
 *
 * The basic submit structure is a required part of every Acumulus API request and
 * contains authentication and client system information, and options that influence the
 * processing or how the response is constructed.
 *
 * @property ?string $format
 * @property ?string $testMode
 * @property ?string $lang
 * @property ?string $iNodes
 * @property ?string $oNodes
 * @property ?string $order
 *
 * @method bool setFormat(?string $value, int $mode = PropertySet::Always)
 * @method bool setTestMode(?int $value, int $mode = PropertySet::Always)
 * @method bool setLang(?string $value, int $mode = PropertySet::Always)
 * @method bool setINodes(?string $value, int $mode = PropertySet::Always)
 * @method bool setONodes(?string $value, int $mode = PropertySet::Always)
 * @method bool setOrder(?string $value, int $mode = PropertySet::Always)
 *
 * @noinspection PhpLackOfCohesionInspection  Data objects have little cohesion.
 */
class BasicSubmit extends AcumulusObject
{
    public bool $needContract = true;

    public ?Contract $contract = null;
    public ?Connector $connector = null;

    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::Format, 'type' => 'string', 'allowedValues' => [Api::Format_Json, Api::Format_Xml]],
            ['name' => Fld::TestMode, 'type' => 'int', 'allowedValues' => [Api::TestMode_Normal, Api::TestMode_Test]],
            ['name' => Fld::Lang, 'type' => 'string', 'allowedValues' => [Api::Lang_NL, Api::Lang_EN]],
            ['name' => Fld::INodes, 'type' => 'string'],
            ['name' => Fld::ONodes, 'type' => 'string'],
            ['name' => Fld::Order, 'type' => 'string', 'allowedValues' => [Api::Order_Default, Api::Order_Inverted]],
        ];
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): void
    {
        $this->contract = $contract;
    }

    public function getConnector(): ?Connector
    {
        return $this->connector;
    }

    public function setConnector(?Connector $connector): void
    {
        $this->connector = $connector;
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->needContract && $this->getContract() !== null) {
            $result[Fld::Contract] = $this->getContract()->toArray();
        }
        $result += parent::toArray();
        if ($this->getConnector() !== null) {
            $result[Fld::Connector] = $this->getConnector()->toArray();
        }
        return $result;
    }
}
