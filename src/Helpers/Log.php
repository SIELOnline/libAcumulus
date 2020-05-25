<?php
namespace Siel\Acumulus\Helpers;

/**
 * Allows to log messages to a log.
 *
 * This base class will log to the PHP error file. It should be overridden per
 * webshop to integrate with the webshop's specific way of logging.
 *
 * @todo: log a Message
 * @todo: log a Message[]
 * @todo: log a MessageCollection
 * @todo: log an exception?
 */
class Log
{
    /** @var int */
    protected $logLevel;

    /** @var string */
    protected $libraryVersion;

    /**
     * Log constructor.
     *
     * @param string $libraryVersion
     */
    public function __construct($libraryVersion)
    {
        $this->libraryVersion = $libraryVersion;
    }

    /**
     * Gets the actual log level.
     *
     * @return int
     *   One of the Severity::... constants.
     */
    public function getLogLevel()
    {
        return $this->logLevel;
    }

    /**
     * Sets the log level, eg. based on configuration.
     *
     * @param int $logLevel
     *   One of the Severity::... constants.
     */
    public function setLogLevel($logLevel)
    {
        $this->logLevel = $logLevel;
    }

    /**
     * @return string
     */
    protected function getLibraryVersion()
    {
        return $this->libraryVersion;
    }

    /**
     * Returns a textual representation of the severity.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     *
     * @return string
     *   A textual representation of the severity.
     */
    protected function getSeverityString($severity)
    {
        $severity = (int) $severity;
        switch ($severity) {
            case Severity::Log:
                return 'Debug';
            case Severity::Info:
                return 'Info';
            case Severity::Notice:
                return 'Notice';
            case Severity::Warning:
                return 'Warning';
            case Severity::Error:
                return 'Error';
            case Severity::Exception:
                return 'Exception';
            default:
                return "Unknown severity $severity";
        }
    }

    /**
     * Formats and logs the message if the log level indicates so.
     *
     * Errors, warnings and notices are always logged, other levels only if the
     * log level is set to do so.
     *
     * Formatting involves:
     * - calling vsprintf() if $args is not empty
     * - adding "Acumulus {version} {severity}: " in front of the message.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     * @param string $message
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through vsprintf().
     * @param array $args
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function log($severity, $message, array $args = array())
    {
        if ($severity >= min($this->getLogLevel(), Severity::Notice)) {
            if (count($args) > 0) {
                $message = vsprintf($message, $args);
            }
            $this->write($message, $severity);
        }
        return $message;
    }

    /**
     * Logs a debug message
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through vsprintf().
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function debug($message)
    {
        $args = func_get_args();
        array_shift($args);
        return $this->log(Severity::Log, $message, $args);
    }

    /**
     * Logs an informational message.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through vsprintf().
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function info($message)
    {
        $args = func_get_args();
        array_shift($args);
        return $this->log(Severity::Info, $message, $args);
    }

    /**
     * Logs a notice.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through vsprintf().
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function notice($message)
    {
        $args = func_get_args();
        array_shift($args);
        return $this->log(Severity::Notice, $message, $args);
    }

    /**
     * Logs a warning.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through vsprintf().
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function warning($message)
    {
        $args = func_get_args();
        array_shift($args);
        return $this->log(Severity::Warning, $message, $args);
    }

    /**
     * Logs an error message.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through vsprintf().
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function error($message)
    {
        $args = func_get_args();
        array_shift($args);
        return $this->log(Severity::Error, $message, $args);
    }

    /**
     * Writes the message to the actual log sink.
     *
     * This base implementation adds the name Acumulus, the version of this
     * library, and the severity and then sends the message to error_log().
     *
     * Override if the web shop offers its own log mechanism.
     *
     * @param string $message
     *   The message to log.
     * @param int $severity
     *   One of the Severity::... constants.
     */
    protected function write($message, $severity)
    {
        $message = sprintf('Acumulus %s: %s - %s', $this->getLibraryVersion(), $this->getSeverityString($severity), $message);
        error_log($message);
    }
}
