<?php
namespace Siel\Acumulus\Helpers;

/**
 * @class \Siel\Acumulus\Helpers\Log.
 */
class Log {

  const None = 0;
  const Error = 1;
  const Warning = 2;
  const Notice = 3;
  const Debug = 4;

  /** @var Log */
  static protected $instance = null;

  /**
   * Log creator.
   *
   * @param int $logLevel
   *   One of the Log level constants from this class.
   */
  public static function createInstance($logLevel) {
    if (static::$instance === null) {
      static::$instance = new static($logLevel);
    }
  }

  /**
   * Returns an instance of the log class (or web shop specific child class).
   *
   * @return Log
   */
  public static function getInstance() {
    return static::$instance;
  }

  /** @var int */
  protected $logLevel;

  /**
   * Log constructor.
   *
   * @param int $logLevel
   *   One of the Log level constants from this class.
   */
  protected function __construct($logLevel) {
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
  protected function getSeverityString($severity) {
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
  public function log($severity, $message, array $args = array()) {
    if (count($args) > 0) {
      $message = vsprintf($message, $args);
    }
    $message = sprintf('Acumulus: %s', $message);
    if ($this->logLevel >= $severity) {
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
  public function debug($message) {
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
  public function notice($message) {
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
  public function warning($message) {
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
  public function error($message) {
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
  protected function write($message, $severity) {
    $message = sprintf('%s - %s', $this->getSeverityString($severity), $message);
    error_log($message);
  }

}
