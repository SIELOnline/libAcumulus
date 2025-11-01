<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use function is_array;

/**
 * Mail contains mail related test functionalities for the various shop-specific test
 * environments.
 *  - Saving and retrieving mail messages.
 *  - Asserting that mail messages match.
 */
trait Mail
{
    use Base;

    /**
     * Returns a test e-mail.
     */
    protected function getTestMail(string $name): ?array
    {
        $mail = null;
        $fullFileName = $this->getDataPath() . "/Mail/$name.mail";
        $phpFileName = "$fullFileName.php";

        if (is_readable($phpFileName)) {
            /** @noinspection UntrustedInclusionInspection The variable contains an absolute path. */
            include $phpFileName;
        } elseif (is_readable($fullFileName)) {
            self::assertTrue(false, 'Switch to use PHP files for mails instead of text files');
            eval('$mail = ' . file_get_contents($fullFileName) . ';');
        }
        /** @noinspection PhpExpressionAlwaysNullInspection */
        return $mail;
    }

    /**
     * Saves test mail data.
     *
     * @param array $data
     *   The mail data to be saved (in JSON format).
     */
    protected function saveTestMail(string $name, array $data): void
    {
        $path = $this->getDataPath() . '/Mail';
        $fileName = "$name.mail";
        if (file_exists("$path/$fileName") || file_exists("$path/$fileName.php")) {
            $fileName = "$name.latest.mail";
        }
        file_put_contents("$path/$fileName", var_export($data, true) . "\n");
    }

    /**
     * Asserts that the contents of the mail sent match the expected contents.
     *
     * It also saves the mail sent using the name and '.latest.mail' as extension.
     *
     * @param string $name
     *   The name under which the mail is saved.
     * @param array|null $mailSent
     *   The mail sent, or null if no mail was sent.
     */
    protected function assertMailMatches(string $name, ?array $mailSent): void
    {
        $this->saveTestMail($name, $mailSent);
        $expected = $this->getTestMail($name);
        if (is_array($expected['bodyText'] ?? null) || is_array($expected['bodyHtml'] ?? null)) {
            // Compare mail contents using str_contains (the contents contain version
            // numbers or so).
            foreach ($expected as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $text) {
                        static::assertStringContainsString($text, $mailSent[$key], $name);
                    }
                } else {
                    static::assertSame($value, $mailSent[$key], $name);
                }
            }
            if (is_array($expected['bodyText'])) {
                foreach ($expected['bodyText'] as $text) {
                    static::assertStringContainsString($text, $mailSent['bodyText'], $name);
                }
            }
            if (is_array($expected['bodyHtml'])) {
                foreach ($expected['bodyHtml'] as $text) {
                    static::assertStringContainsString($text, $mailSent['bodyHtml'], $name);
                }
            }
        } else {
            static::assertSame($expected, $mailSent, $name);
        }
    }
}
