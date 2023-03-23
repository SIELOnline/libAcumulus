<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

/**
 * Collects customer data from the shop.
 *
 * Properties that are mapped:
 * - string $contactId
 * - string $contactYourId
 * - string $website
 * - string $vatNumber
 * - string $telephone
 * - string $telephone2
 * - string $fax
 * - string $email
 * - string $bankAccountNumber
 * - string $mark
 *
 * Properties that are computed using logic:
 * - none
 *
 * Properties that are based on configuration and thus are not set here:
 * - int $type
 * - int $vatTypeId
 * - int $contactStatus
 * - int $overwriteIfExists
 * - int $disableDuplicates
 *
 * Properties that are not set:
 * - none
 *
 * Note that all address data, shipping and invoice address, are placed in
 * separate {@see \Siel\Acumulus\Data\Address} objects.
 */
class CustomerCollector extends Collector
{
}
