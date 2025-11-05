<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use DateTimeImmutable;

use Siel\Acumulus\Mail\Mailer;

use function is_array;
use function strlen;

/**
 * Mail contains mail related test functionalities for library and shop tests.
 *  - Saving and retrieving mail messages.
 *  - Asserting that mail messages match.
 */
trait Mail
{
    use AcumulusContainer;
    use Path;
    use Time;

    private function getMailer(): Mailer
    {
        return static::getContainer()->getMailer();
    }

    /**
     * Returns a test e-mail.
     */
    protected static function getTestMail(string $name): ?array
    {
        $mail = null;
        $fullFileName = self::getDataPath() . "/Mail/$name.mail";
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
    protected static function saveTestMail(string $name, array $data): void
    {
        $path = self::getDataPath() . '/Mail';
        $fileName = "$name.mail";
        if (file_exists("$path/$fileName") || file_exists("$path/$fileName.php")) {
            $fileName = "$name.latest.mail";
        }
        file_put_contents("$path/$fileName", var_export($data, true) . "\n");
    }

    /**
     * Checks the mail messages.
     */
    protected function checkMail(int $mailCount): void
    {
        static::assertSame($mailCount + 1, $this->getMailer()->getMailCount());
        $mailSent = $this->getMailer()->getMailSent($mailCount);
        static::assertIsArray($mailSent);

        // dataName() returns the key of the actual data set.
        $name = str_replace(' ', '-', $this->dataName()) . '-' . static::getContainer()->getLanguage();
        self::assertMailMatches($name, $mailSent);
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
    protected static function assertMailMatches(string $name, ?array $mailSent): void
    {
        self::saveTestMail($name, $mailSent);
        $expected = self::getTestMail($name);
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
                    static::assertStringContainsString($text, $mailSent['bodyText'], "$name HTML part does not match");
                }
            }
            if (is_array($expected['bodyHtml'])) {
                foreach ($expected['bodyHtml'] as $text) {
                    static::assertStringContainsString($text, $mailSent['bodyHtml'], "$name text part does not match");
                }
            }
        } else {
            static::assertSame($expected, $mailSent, $name);
        }
    }

    /**
     * Tests that mail sending (still) works.
     */
    protected function _testMailer(bool $hasTextPart = true, bool $hasHtmlPart = true, bool $isBase64 = false): void
    {
        $shopName = str_replace('\\', '_', self::getContainer()->getShopNamespace());
        $subject = "___$shopName test mail___";
        $bodyText = 'Text test message';
        $bodyHtml = '<p>HTML Test message</p>';
        $mailer = self::getContainer()->getMailer();
        self::assertTrue($mailer->sendAdminMail($subject, $bodyText, $bodyHtml), 'Sending mail failed');
        self::assertMailServerReceivedMail($subject, $hasTextPart ? $bodyText : null, $hasHtmlPart ? $bodyHtml : null, $isBase64);
    }

    /**
     * This method checks that a mail has been sent and received by the mail server.
     *
     * It is based on the knowledge that:
     * - In my local test environments, the "PaperCut SMTP" app is used to facilitate
     *   testing the mail sending process.
     * - The "PaperCut SMTP" app logs all its activity to a local folder:
     *   C:\ProgramData\Changemaker Studios\Papercut SMTP\Incoming
     *   - Activity log: {local log folder}\Papercut.Service.log
     *   - Mail message log: {local log folder}\{timestamp}{cut off subject}{unique tag}.eml
     * - For Magento the log folder is mount to /home/erwin/Papercut-SMTP.
     *
     * This method searches the file 'Papercut.Service.log' for the last line containing
     * something like:
     * 2025-10-30 15:07:06.116 +01:00 [INF] Successfully Saved email message:
     *   {log folder}\20251030150706091 Invoice sent to Acumulus in test mode_ c62f22.eml
     * and checks that the subject appears in the mentioned file name.
     * Then it checks that the file contains the mail contents: subject and body (2 mime
     * parts for the text and html versions of the body).
     */
    protected static function assertMailServerReceivedMail(string $subject, ?string $bodyText, ?string $bodyHtml, bool $isBase64 = false): void
    {
        // Give mail serer time to process the mail and save the log file.
        sleep(1);
        $now = new DateTimeImmutable();
        $papercutLog = self::getPapercutLogFile();
        $logLines = array_reverse(explode("\n", str_replace(["\r\n", "\r"], "\n", self::tail($papercutLog, 50))));
        foreach ($logLines as $line) {
            $logMessage = 'Successfully Saved email message';
            $logMessageStart = strpos($line, $logMessage);
            if ($logMessageStart !== false && str_contains($line, $subject)) {
                // Line looks like {timestamp} [{level}] {message}
                $timestamp = substr($line, 0, strpos($line, '[') - 1);
                $diff = self::getDiffInSeconds(new DateTimeImmutable($timestamp), $now);
                if (0 <= $diff && $diff < 10) {
                    // Message looks like:
                    // Successfully Saved email message: {log folder}\{eml file name}.eml
                    // We want the full file name, so we can check the file contents.
                    $emlFile = substr($line, $logMessageStart + strlen($logMessage) + strlen(': '));
                    $emlFile = substr($emlFile, 0, strpos($emlFile, '.eml') + strlen('.eml'));
                    self::assertMailSentContainsParts($emlFile, $bodyText, $bodyHtml, $isBase64);
                    return;
                } else {
                    self::fail('Log mentions a mail being sent, but more then 10 seconds ago.');
                }
            }
        }
        self::fail('Log does not confirm that the mail was sent');
    }

    protected static function assertMailSentContainsParts(string $emlFile, ?string $bodyText, ?string $bodyHtml, bool $isBase64): void
    {
        $mailMessageContents = file_get_contents($emlFile);
        if ($bodyText !== null) {
            if ($isBase64) {
                $bodyText = base64_encode($bodyText);
            }
            static::assertStringContainsString($bodyText, $mailMessageContents, 'text mime part not found in message');
        }
        if ($bodyHtml !== null) {
            if ($isBase64) {
                $bodyHtml = base64_encode($bodyHtml);
            }
            static::assertStringContainsString($bodyHtml, $mailMessageContents, 'HTML mime part not found in message');
        }
    }

    /**
     * Returns the path to the Papercut Service log file.
     */
    protected static function getPapercutLogFile(): string
    {
        return self::getPapercutFolder() . '/Papercut.Service.log';
    }

    /**
     * Returns the folder where the Papercut SMTP Service log file and saved messages are
     * located.
     */
    protected static function getPapercutFolder(): string
    {
        return 'C:\ProgramData\Changemaker Studios\Papercut SMTP\Incoming';
    }

    /**
     * Returns last $lines of lines of file $filePath.
     *
     * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     *
     * @author Torleif Berger, Lorenzo Stanco
     * @link http://stackoverflow.com/a/15025877/995958
     * @license http://creativecommons.org/licenses/by/3.0/
     */
    private static function tail(string $filepath, int $lines = 1, bool $adaptive = true): ?string
    {
        // Open file
        $f = fopen($filepath, 'rb');
        if ($f === false) {
            return null;
        }

        // Sets buffer size, according to the number of lines to retrieve.
        // This gives a performance boost when reading a few lines from the file.
        if (!$adaptive) {
            $buffer = 4096;
        } else {
            $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
        }

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) !== "\n") {
            $lines--;
        }

        // Start reading
        $output = '';
        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {
            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);
            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);
            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)) . $output;
            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {
            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }

        // Close file and return
        fclose($f);
        return trim($output);
    }
}
