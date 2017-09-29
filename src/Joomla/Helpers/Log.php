<?php
namespace Siel\Acumulus\Joomla\Helpers;

use JLog;
use Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the Joomla log.
 */
class Log extends BaseLog
{
    public function __construct()
    {
        parent::__construct();
        JLog::addLogger(array('text_file' => 'acumulus.log.php'),
            JLog::ALL,
            array('com_acumulus')
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
     *   One of the constants of the base Log class.
     *
     * @return int
     *   the Joomla equivalent of the severity.
     */
    protected function getJoomlaSeverity($severity)
    {
        switch ($severity) {
            case Log::Error:
                return JLog::ERROR;
            case Log::Warning:
                return JLog::WARNING;
            case Log::Notice:
                return JLog::NOTICE;
            case Log::Info:
                return JLog::INFO;
            case Log::Debug:
            default:
                return JLog::DEBUG;
        }
    }
}
