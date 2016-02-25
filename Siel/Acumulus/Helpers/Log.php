<?php
namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Web\ConfigInterface;

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
    const Debug = 4;
    const NotYetSet = 5;

    /** @var Log */
    static protected $instance = null;

    /**
     * Returns an instance of the log class (or web shop specific child class).
     *
     * @return Log
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /** @var int */
    protected $logLevel;

    /** @var ConfigInterface */
    protected $config;

    /**
     * Log constructor.
     *
     * @param ConfigInterface $config
     */
    public function __construct($config)
    {
        // Start with logging everything. Soon after the creation of this log object
        // the log level should be set based on the configuration.
        $this->logLevel = static::NotYetSet;
        $this->config = $config;
        static::$instance = $this;
    }

    /**
     * Gets the actual log level.
     *
     * @return int
     */
    public function getLogLevel()
    {
        // To support lazy load of the config, the log level is not yet set until
        // actually needed.
        if ($this->logLevel === static::NotYetSet && $this->config !== null) {
            $this->logLevel = $this->config->getLogLevel();
        }
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
            case Log::Debug:
            default:
                return 'Debug';
        }
    }

    /**
     * Formats and logs the message if the severity equals or is worse than the
     * current log level.
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
        if ($this->getLogLevel() >= $severity) {
            if (count($args) > 0) {
                $message = vsprintf($message, $args);
            }
            $message = sprintf('Acumulus: %s', $message);
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
        $message = sprintf('%s - %s', $this->getSeverityString($severity), $message);
        error_log($message);
    }
}
