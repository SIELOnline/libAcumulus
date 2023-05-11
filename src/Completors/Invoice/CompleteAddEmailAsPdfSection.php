<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;

use Siel\Acumulus\Data\Invoice;

use Siel\Acumulus\Meta;

use function assert;

/**
 * CompleteAddEmailAsPdfSection adds the 'emailAsPdf' setting as metadata.
 */
class CompleteAddEmailAsPdfSection extends BaseCompletorTask
{
    /**
     * Adds the 'emailAsPdf' setting as metadata.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int ...$args
     *   Additional parameters: none.
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        $addEmailAsPdfSection = $this->configGet('emailAsPdf');
        $acumulusObject->metadataSet(Meta::AddEmailAsPdfSection, $addEmailAsPdfSection);
    }
}
