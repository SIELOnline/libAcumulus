<?php
/**
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;

use function sprintf;

/**
 * Extends the base log class to log any library logging to the PrestaShop log.
 */
abstract class Log extends BaseLog
{
    protected string $filename = 'acumulus.log';

    /**
     * @return \Opencart\System\Library\Log|\Log
     */
    abstract protected function getLog();

    /**
     * {@inheritdoc}
     *
     * This override uses the OpenCart Log class.
     *
     * @noinspection PhpMissingParentCallCommonInspection parent is default
     *   fall back.
     */
    protected function write(string $message, int $severity): void
    {
        $this->getLog()->write(sprintf('%s - %s', $this->getSeverityString($severity), $message));
    }
}
