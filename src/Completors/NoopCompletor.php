<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\MessageCollection;

/**
 * NoopCompletor is a completor that does not complete anything.
 *
 * Noop stands for "no operation".
 */
class NoopCompletor extends BaseCompletor
{
    /**
     * {@inheritdoc}
     *
     * This noop override does not change the $acumulusObject in any way nor does it
     * change the $result.
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
    }
}
