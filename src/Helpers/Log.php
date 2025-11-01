<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Meta;
use Throwable;

use function count;
use function get_class;
use function sprintf;

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
     * Set of json_encode flags we use to improve the readability of log messages.
     * See {@see \Siel\Acumulus\Meta::JsonFlags}.
     */
    public const JsonFlags = Meta::JsonFlags | JSON_PRETTY_PRINT;

    private int $logLevel;
    private string $libraryVersion;
    /**
     * @var array[]
     */
    protected array $loggedMessages = [];

    /**
     * Log constructor.
     *
     * @param string $libraryVersion
     *   The version of the library. It will be logged with each log message,
     *   allowing better interpreting old log messages when giving support.
     */
    public function __construct(string $libraryVersion)
    {
        $this->libraryVersion = $libraryVersion;
    }

    /**
     * Gets the actual log level.
     *
     * @return int
     *   One of the {@see Severity} constants.
     */
    public function getLogLevel(): int
    {
        if (!isset($this->logLevel)) {
            $pluginSettings = Container::getContainer()->getConfig()->getPluginSettings();
            $this->setLogLevel($pluginSettings['logLevel']);
        }
        return $this->logLevel;
    }

    /**
     * Sets the log level, e.g. based on configuration.
     *
     * @param int $logLevel
     *   One of the {@see Severity} constants: Log, Info, Notice, Warning, Error,
     *   or Exception
     */
    public function setLogLevel(int $logLevel): void
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

    protected function addLoggedMessage(int $severity, string $message, string $format, array $values): void
    {
        $this->loggedMessages[] = compact('message', 'severity', 'format', 'values');
    }

    protected function hasBeenLogged(string $message): bool
    {
        foreach ($this->loggedMessages as $loggedMessage) {
            if ($loggedMessage['message'] === $message || $loggedMessage['format'] === $message) {
                return true;
            }
        }
        return false;
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
        return match ($severity) {
            Severity::Log => 'Debug',
            Severity::Success => 'Success',
            Severity::Info => 'Info',
            Severity::Notice => 'Notice',
            Severity::Warning => 'Warning',
            Severity::Error => 'Error',
            Severity::Exception => 'Exception',
            default => "Unknown severity $severity",
        };
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
        return $this->log(Severity::Log, $message, ...$values);
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
    public function success(string $message, ...$values): string
    {
        return $this->log(Severity::Success, $message, ...$values);
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
    public function info(string $message, ...$values): string
    {
        return $this->log(Severity::Info, $message, ...$values);
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
    public function notice(string $message, ...$values): string
    {
        return $this->log(Severity::Notice, $message, ...$values);
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
    public function warning(string $message, ...$values): string
    {
        return $this->log(Severity::Warning, $message, ...$values);
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
    public function error(string $message, ...$values): string
    {
        return $this->log(Severity::Error, $message, ...$values);
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
    public function exception(Throwable $e, bool $includeTrace = false): string
    {
        $callingFunction = $e->getTrace()[0]['function'];
        $callingLine = $e->getLine();
        $class = get_class($e);
        $pos = strrpos($class, '\\');
        if ($pos !== false) {
            $class = substr($class, $pos + 1);
        }
        $code = !empty($e->getCode()) && !str_contains($e->getMessage(), (string) $e->getCode()) ? $e->getCode() . ': ' : '';
        $message = $e->getMessage();
        $fullMessage = "$class: $code$message in $callingFunction:$callingLine";
        if (!$this->hasBeenLogged($fullMessage)) {
            $this->log(Severity::Exception, $fullMessage);
            if ($includeTrace) {
                $this->log(Severity::Exception, $e->getTraceAsString());
            }
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
     *   One of the {@see Severity} constants.
     */
    protected function write(string $message, int $severity): void
    {
        $message = sprintf('Acumulus %s: %s - %s', $this->getLibraryVersion(), $this->getSeverityString($severity), $message);
        /** @noinspection ForgottenDebugOutputInspection */
        error_log($message);
    }

    /**
     * Formats and logs the message if the log level indicates so.
     *
     * Errors, warnings, and notices are always logged, other levels only if the
     * log level is set to do so. Before the log level is set from config,
     * informational messages are also logged.
     *
     * Formatting involves:
     * - calling {@see vsprintf()} if $args is not empty.
     * - adding "Acumulus {version} {severity}:" in front of the message.
     *
     * @param int $severity
     *   One of the {@see Severity} constants.
     * @param string $message
     *   The message to log, optionally followed by arguments. If there are
     *   arguments the $message is passed through {@see vsprintf()}.
     * @param mixed $values
     *   Any values to replace %-placeholders in $message.
     *
     * @return string
     *   The formatted message whether it got logged or not.
     */
    public function log(int $severity, string $message, ... $values): string
    {
        $format = $message;
        if (count($values) > 0) {
            $message = vsprintf($format, $values);
        }
        $this->addLoggedMessage($severity, $message, $format, $values);
        if ($severity >= $this->getLogLevel()) {
            $this->write($message, $severity);
        }
        return $message;
    }
}
