<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * EmailAsPdfTarget defines the possible target messages for an EmailAsPdfTarget
 * section.
 *
 * PHP8.1: enumeration.
 */
interface EmailAsPdfTarget
{
    public const Invoice = 'invoice';
    public const PackingSlip = 'packingslip';
}
