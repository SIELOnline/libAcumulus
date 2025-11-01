<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use function is_array;

/**
 * Log contains log related test functionalities for the various shop-specific test
 * environments.
 *
 * - Saving and retrieving log messages.
 * - Asserting that log messages match.
 */
trait Log
{
    use Base;

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
}
