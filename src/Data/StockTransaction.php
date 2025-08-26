<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTimeInterface;
use Siel\Acumulus\Fld;

/**
 * Represents an Acumulus API Stock transaction object.
 *
 * The definition of the fields is based on the
 * {@link https://www.siel.nl/acumulus/API/Stock/Add_Stock_Transaction/ Add Stock Transaction API call},
 * NOT the
 * {@link https://www.siel.nl/acumulus/API/Contacts/Manage_Contact/ Manage Contact call}.
 * However, there are some notable changes with the API structure:
 * - A Customer is part of the {@see Invoice} instead of the other way in the
 *   API.
 * - We have 2 separate {@see Address} objects, an invoice and billing address.
 *   In the API all address fields are part of the customer itself, the fields
 *   of the 2nd address being prefixed with 'alt'. In decoupling this in the
 *   collector phase, we allow users to relate the 1st and 2nd address to the
 *   invoice or shipping address as they like.
 *
 * @property ?int $productId
 * @property ?float $stockAmount
 * @property ?string $stockDescription
 * @property ?DateTimeInterface $stockDate
 *
 * @method bool setProductId(?int $value, int $mode = PropertySet::Always)
 * @method bool setStockAmount(?float $value, int $mode = PropertySet::Always)
 * @method bool setStockDescription(?string $value, int $mode = PropertySet::Always)
 * @method bool setStockDate(?DateTimeInterface $value, int $mode = PropertySet::Always)
 */
class StockTransaction extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::ProductId, 'type' => 'int', 'required' => true],
            ['name' => Fld::StockAmount, 'type' => 'float', 'required' => true],
            ['name' => Fld::StockDescription, 'type' => 'string'],
            ['name' => Fld::StockDate, 'type' => 'date'],
        ];
    }

    public function toArray(): array
    {
        return [Fld::Stock => parent::toArray()];
    }
}
