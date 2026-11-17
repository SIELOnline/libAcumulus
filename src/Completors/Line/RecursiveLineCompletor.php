<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Line;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;

/**
 * RecursiveLineCompletor calls the complete task for the given line and all its children.
 */
abstract class RecursiveLineCompletor extends BaseCompletorTask
{
    /**
     * Completes 1 line while recursively going through a main Line and all its children.
     */
    abstract protected function complete1(Line $line, ...$args): void;

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     */
    final public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $this->complete1($acumulusObject, ...$args);
        foreach ($acumulusObject->getChildren() as $child) {
            $this->complete($child, ...$args);
        }
    }
}
