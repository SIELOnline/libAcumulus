<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

use Joomla\CMS\Log\Log as JoomlaLog;
use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;

/**
 * Extends the base log class to log any library logging to the Joomla log.
 */
class Log extends BaseLog
{
    protected string $category;

    public function __construct(string $libraryVersion)
    {
        parent::__construct($libraryVersion);
        $this->category = 'com_acumulus_' . $this->getLibraryVersion();
        JoomlaLog::addLogger(
            ['text_file' => 'acumulus.log.php'],
            JoomlaLog::ALL,
            [$this->category],
        );
    }

    /**
     * {@inheritdoc}
     *
     * This override uses JoomlaLog.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function write(string $message, int $severity): void
    {
        JoomlaLog::add($message, $this->getJoomlaSeverity($severity), $this->category);
    }

    /**
     * Returns the joomla equivalent of the severity.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     *
     * @return int
     *   the Joomla equivalent of the severity.
     */
    protected function getJoomlaSeverity(int $severity): int
    {
        switch ($severity) {
            case Severity::Exception:
            case Severity::Error:
                return JoomlaLog::ERROR;
            case Severity::Warning:
                return JoomlaLog::WARNING;
            case Severity::Notice:
                return JoomlaLog::NOTICE;
            case Severity::Info:
                return JoomlaLog::INFO;
            case Severity::Success:
            case Severity::Log:
            default:
                return JoomlaLog::DEBUG;
        }
    }
}
