<?php
namespace Siel\Acumulus\Helpers;

/**
 * Defines a logger class. This class is supposed to be overridden by shop
 * specific classes to integrate with the shop specific way of logging.
 */
class Log
{
    const None = 0;
    const Error = 1;
    const Warning = 2;
    const Notice = 3;
    const Info = 4;
    const Debug = 5;
    const NotYetSet = 6;

    /** @var int */
    protected $logLevel;

    /** @var string */
    protected $libraryVersion;

    /**
     * Log constructor.
     */
    public function __construct()
    {
        // Start with logging everything. Soon after the creation of this log object
        // the log level should be set based on the configuration.
        $this->setLogLevel(static::NotYetSet);
        $this->setLibraryVersion('version not yet set');
    }

    /**
     * Gets the actual log level.
     *
     * @return int
     */
    public function getLogLevel()
    {
        return $this->logLevel;
    }

    /**
     * Sets the log level, eg. based on configuration.
     *
     * @param int $logLevel
     */
    public function setLogLevel($logLevel)
    {
        $this->logLevel = $logLevel;
    }

    /**
     * @return string
     */
    public function getLibraryVersion()
    {
        return $this->libraryVersion;
    }

    /**
     * @param string $libraryVersion
     */
    public function setLibraryVersion($libraryVersion)
    {
        $this->libraryVersion = $libraryVersion;
    }


    /**
     * Returns a textual representation of the severity.
     *
     * @param int $severity
     *   One of the constants of this class.
     *
     * @return string
     *   A textual representation of the severity.
     */
    protected function getSeverityString($severity)
    {
        switch ($severity) {
            case Log::Error:
                return 'Error';
            case Log::Warning:
                return 'Warning';
            case Log::Notice:
                return 'Notice';
            case Log::Info:
                return 'Info';
            case Log::Debug:
            default:
                return 'Debug';
        }
    }

    /**
     * Formats and logs the message if the log level indicates so.
     *
     * Errors and Warnings are always logged, other levels only if the log level
     * is set to do so.
     *
     * Formatting involves:
     * - calling vsprintf() if $args is not empty
     * - adding "Acumulus {version} {severity}: " in front of the message.
     *
     * @param int $severity
     * @param string $message
     * @param array $args
     *
     * @return string
     *   The full formatted message whether it got logged or not.
     */
    public function log($severity, $message, array $args = array())
    {
        if ($severity <= max($this->getLogLevel(), Log::Warning)) {
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
        return $this->log(Log::Debug, $message, $args);
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
        return $this->log(Log::Notice, $message, $args);
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
        return $this->log(Log::Info, $message, $args);
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
        return $this->log(Log::Warning, $message, $args);
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
        return $this->log(Log::Error, $message, $args);
    }

    /**
     * Writes the message to the actual log sink.
     *
     * This base implementation sends the message to error_log(). Override if the
     * web shop offers its own log mechanism.
     *
     * @param string $message
     * @param int $severity
     */
    protected function write($message, $severity)
    {
        $message = sprintf('Acumulus %s: %s - %s', $this->libraryVersion, $this->getSeverityString($severity), $message);
        error_log($message);
    }
}
