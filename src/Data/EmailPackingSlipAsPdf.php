<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * Represents an emailAsPdf part of an Acumulus API e-mail packing slip request.
 *
 * Field names are copied from the API, though capitals are introduced for
 * readability and to prevent PhpStorm typo inspections.
 *
 * Metadata can be added via the {@see MetadataCollection} methods.
 */
class EmailPackingSlipAsPdf extends EmailAsPdf
{
}
