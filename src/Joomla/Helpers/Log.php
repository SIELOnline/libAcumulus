<?php
namespace Siel\Acumulus\Joomla\Helpers;

use JLog;
use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;

/**
 * Extends the base log class to log any library logging to the Joomla log.
 */
class Log extends BaseLog
{

    /**
     * {@inheritdoc}
     */
    public function __construct($libraryVersion)
    {
        parent::__construct($libraryVersion);
        JLog::addLogger(['text_file' => 'acumulus.log.php'],
            JLog::ALL,
            ['com_acumulus']
        );
    }

    /**
     * {@inheritdoc}
     *
     * This override uses JLog.
     */
    protected function write($message, $severity)
    {
        jimport('joomla.log.log');
        JLog::add($message, $this->getJoomlaSeverity($severity), 'com_acumulus');
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
    protected function getJoomlaSeverity($severity)
    {
        switch ($severity) {
            case Severity::Error:
                return JLog::ERROR;
            case Severity::Warning:
                return JLog::WARNING;
            case Severity::Notice:
                return JLog::NOTICE;
            case Severity::Info:
                return JLog::INFO;
            case Severity::Log:
            default:
                return JLog::DEBUG;
        }
    }
}
