<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use Siel\Acumulus\Helpers\Severity;

use function ini_get;
use function is_array;

/**
 * Log contains log related test functionalities for library and shop tests.
 *
 * - Saving and retrieving log messages.
 * - Asserting that log messages match.
 * - Asserting that log messages get logged in the log file, or, depending on the log
 *   level, do not get logged.
 */
trait Log
{
    use Path;

    /**
     * Returns a test log message.
     */
    protected function getTestLogMessage(string $name): ?array
    {
        $logMessage = null;
        $fullFileName = $this->getDataPath() . "/Log/$name.log";
        if (is_readable($fullFileName)) {
            eval('$logMessage = ' . file_get_contents($fullFileName) . ';');
        }
        /** @noinspection PhpExpressionAlwaysNullInspection */
        return $logMessage;
    }

    /**
     * Saves test log messages.
     *
     * @param array $data
     *   The mail data to be saved (in JSON format).
     */
    protected function saveTestLogMessage(string $name, array $data): void
    {
        $path = $this->getDataPath() . '/Log';
        $fileName = "$name.log";
        if (file_exists("$path/$fileName") || file_exists("$path/$fileName.php")) {
            $fileName = "$name.latest.log";
        }
        file_put_contents("$path/$fileName", var_export($data, true) . "\n");
    }

    /**
     * Asserts that the contents of the mail sent match the expected contents.
     *
     * It also saves the mail sent using the name and '.latest.mail' as extension.
     *
     * @param string $name
     *   The name under which the log message was saved.
     * @param array $logMessage
     *   The mail sent, or false if no log was sent, though false will lead to an
     *   immediate test assertion failure.
     */
    protected function assertLogMatches(string $name, mixed $logMessage): void
    {
        self::assertIsArray($logMessage);
        $this->saveTestLogMessage($name, $logMessage);
        $expected = $this->getTestLogMessage($name);
        foreach ($expected as $key => $value) {
            if ($key === 'values') {
                foreach ($value as $index => $argument) {
                    if (is_array($argument)) {
                        foreach ($argument as $subString => $text) {
                            static::assertStringContainsString($text, $logMessage[$key][$index], "$name-$key-$index-$subString");
                        }
                    } else {
                        static::assertSame($argument, $logMessage[$key][$index], "$name-$key-$index");
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $subString => $text) {
                    static::assertStringContainsString($text, $logMessage[$key], "$name-$key-$subString");
                }
            } else {
                static::assertSame($value, $logMessage[$key], "$name-$key");
            }
        }
    }

    /**
     * Returns the path to the acumulus log file.
     *
     * The default implementation returns the path to the PHP error log, as that is the
     * default where {@see \Siel\Acumulus\Helpers\Log} to logs. However, as all shops
     * override this, this method should be overridden by all shops...
     */
    protected function getLogPath(): string
    {
        return ini_get('error_log');
    }

    /**
     * Returns the file size of the given log file (or the
     * {@see getLogPath() default log file} if none passed), 0 if the log file does not
     * yet exist.
     */
    protected function getLogSize(?string $logFile = null): int
    {
        clearstatcache();
        $logFile ??= $this->getLogPath();
        return file_exists($logFile) ? filesize($logFile) : 0;
    }

    /**
     * Tests that the log works as expected.
     *
     * 2 messages are logged, the first with a level that should not be logged, whereas
     * the second should be logged. We check that the log file does not grow after logging
     * the first message but does so after logging the 2nd message.
     */
    protected function _testLog(): void
    {
        $size = $this->getLogSize();
        $logger = self::getContainer()->getLog();
        $logger->setLogLevel(Severity::Info);
        $logger->debug('Log::testLog() message 1');
        static::assertSame($size, $this->getLogSize());
        $logger->info('Log::testLog() message 2');
        static::assertGreaterThan($size, $this->getLogSize());
    }
}
