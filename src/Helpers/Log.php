<?php
/**
 * Note: As long as we want to check for a minimal PHP version via the
 * Requirements checking process provided by the classes below, and we want to
 * properly log and inform the user, we should not use PHP7 language constructs
 * in the following classes (and its child classes):
 * - {@see Container}: creates instances of the below classes.
 * - {@see Requirements}: executes the checks.
 * - {@see \Siel\Acumulus\Config\ConfigUpgrade}: initiates the check.
 * - {@see \Siel\Acumulus\Helpers\Severity}: part of a failed check.
 * - {@see \Siel\Acumulus\Helpers\Message}: represents a failed check.
 * - {@see \Siel\Acumulus\Helpers\MessageCollection}: represents failed checks.
 * - {@see Log}: Logs failed checks.
 *
 * The PHP7 language constructs we suppress the warnings for:
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 */

namespace Siel\Acumulus\Helpers;

/**
 * Allows logging messages to a log.
 *
 * This base class will log to the PHP error file. It should be overridden per
 * web shop to integrate with the web shop's specific way of logging.
 *
 * @todo: log a Message
 * @todo: log a Message[]
 * @todo: log a MessageCollection
 * @todo: log an exception?
 */
class Log
{
    /** @var int */
    protected $logLevel = Severity::Info;

    /** @var string */
    protected $libraryVersion;

    /**
     * Log constructor.
     *
     * @param string $libraryVersion
     *   The version of the library. It will be logged with each log message,
     *   allowing to better interpret old log messages when giving support.
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
     * Sets the log level, e.g. based on configuration.
     *
     * @param int $logLevel
     *   One of the Severity::... constants: Log, Info, Notice, Warning, Error,
     *   or Exception
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
     * log level is set to do so. Before the log level is set from config,
     * informational messages are also logged.
     *
     * Formatting involves:
     * - calling vsprintf() if $args is not empty.
     * - adding "Acumulus {version} {severity}: " in front of the message.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     * @param string $message
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through vsprintf().
     * @param array $args
     *   Any arguments to replace % placeholders in $message.
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function log($severity, $message, array $args = [])
    {
        if (count($args) > 0) {
            $message = vsprintf($message, $args);
        }
        if ($severity >= $this->getLogLevel()) {
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
        return $this->log(Severity::Log, ...func_get_args());
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
        return $this->log(Severity::Info, ...func_get_args());
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
        return $this->log(Severity::Notice, ...func_get_args());
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
        return $this->log(Severity::Warning, ...func_get_args());
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
        return $this->log(Severity::Error, ...func_get_args());
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
