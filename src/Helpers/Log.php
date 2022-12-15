<?php
namespace Siel\Acumulus\Helpers;

use Throwable;

/**
 * Allows logging messages to a log.
 *
 * This base class will log to the PHP error file. It should be overridden per
 * web shop to integrate with the web shop's specific way of logging.
 *
 * @todo: log a Message
 * @todo: log a Message[]
 * @todo: log a MessageCollection
 * @todo: start using logging an exception?
 */
class Log
{
    /**
     * Enhanced set of json_encode flags we use to improve readability of log
     * messages.
     * See {@see \Siel\Acumulus\Meta::JsonFlags} and {@see Util::JsonFlags}.
     */
    public const JsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /** @var int */
    protected $logLevel = Severity::Info;

    /** @var string */
    protected $libraryVersion;

    /**
     * @var array[]
     */
    protected $loggedMessages = [];

    /**
     * Log constructor.
     *
     * @param string $libraryVersion
     *   The version of the library. It will be logged with each log message,
     *   allowing to better interpret old log messages when giving support.
     */
    public function __construct(string $libraryVersion)
    {
        $this->libraryVersion = $libraryVersion;
    }

    /**
     * Gets the actual log level.
     *
     * @return int
     *   One of the Severity::... constants.
     */
    public function getLogLevel(): int
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
    public function setLogLevel(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    /**
     * Returns a list of all logged messages.
     *
     * This can be used to prevent logging some messages more than once.
     *
     * @return array[]
     *  An array with all messages logged during this request. Each entry is an
     *  array with keys:
     *  - message (string): the formatted message that has been logged
     *  - severity (int): the severity with which this message was logged.
     */
    public function getLoggedMessages(): array
    {
        return $this->loggedMessages;
    }

    protected function getLibraryVersion(): string
    {
        return $this->libraryVersion;
    }

    /**
     * Returns a textual representation of the severity.
     */
    protected function getSeverityString($severity): string
    {
        $severity = (int) $severity;
        switch ($severity) {
            case Severity::Log:
                return 'Debug';
            case Severity::Success:
                return 'Success';
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
     * Logs a debug message
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param mixed ...$values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function debug(string $message, ...$values): string
    {
        return $this->log(Severity::Log, $message, $values);
    }

    /**
     * Logs a success message.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param mixed ...$values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function success(string $message, ... $values): string
    {
        return $this->log(Severity::Success, $message, $values);
    }

    /**
     * Logs an informational message.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param mixed ...$values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function info(string $message, ... $values): string
    {
        return $this->log(Severity::Info, $message, $values);
    }

    /**
     * Logs a notice.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param mixed ...$values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function notice(string $message, ... $values): string
    {
        return $this->log(Severity::Notice, $message, $values);
    }

    /**
     * Logs a warning.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param mixed ...$values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function warning(string $message, ... $values): string
    {
        return $this->log(Severity::Warning, $message, $values);
    }

    /**
     * Logs an error message.
     *
     * @param string $message,...
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param mixed ...$values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function error(string $message, ... $values): string
    {
        return $this->log(Severity::Error, $message, $values);
    }

    /**
     * Logs an exception message.
     *
     * @param \Throwable $e
     *   The "exception" to log.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function exception(Throwable $e): string
    {
        $callingFunction = $e->getTrace()[0]['function'];
        $callingLine = $e->getLine();
        $class = get_class($e);
        $pos = strrpos($class, '\\');
        if ($pos !== false) {
            $class = substr($class, $pos + 1);
        }
        $code = !empty($e->getCode()) && strpos($e->getMessage(), (string) $e->getCode()) === false ? $e->getCode() . ': ' : '';
        $message = $e->getMessage();
        $fullMessage = "$class: $code$message in $callingFunction:$callingLine";
        if (empty($e->hasBeenLogged)) {
            $this->log(Severity::Exception, $fullMessage);
            $e->hasBeenLogged = true;
        }
        return $fullMessage;
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
    protected function write(string $message, int $severity)
    {
        $message = sprintf('Acumulus %s: %s - %s', $this->getLibraryVersion(), $this->getSeverityString($severity), $message);
        error_log($message);
    }

    /**
     * Formats and logs the message if the log level indicates so.
     *
     * Errors, warnings and notices are always logged, other levels only if the
     * log level is set to do so. Before the log level is set from config,
     * informational messages are also logged.
     *
     * Formatting involves:
     * - calling {@see vsprintf()} if $args is not empty.
     * - adding "Acumulus {version} {severity}: " in front of the message.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     * @param string $message
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param array $values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function log(int $severity, string $message, array $values = []): string
    {
        $format = $message;
        if (count($values) > 0) {
            $message = vsprintf($format, $values);
        }
        $this->loggedMessages[] = ['message' => $message, 'severity' => $severity, 'format' => $format, 'values' => $values];
        if ($severity >= $this->getLogLevel()) {
            $this->write($message, $severity);
        }
        return $message;
    }
}
