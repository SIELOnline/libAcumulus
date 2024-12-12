<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\ExpectationFailedException;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Translations;

use function in_array;
use function is_float;
use function is_string;

/**
 * AcumulusTestUtils contains test functionalities for the various shop specific test
 * environments.
 */
trait AcumulusTestUtils
{
    protected static string $htmlStart = <<<LONGSTRING
<!DOCTYPE html>
<!--suppress GrazieInspection, HtmlUnknownTarget -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>

LONGSTRING;
    protected static string $htmlEnd = <<<LONGSTRING

</body>
</html>
LONGSTRING;

    private static Container $container;

    /**
     * Returns an Acumulus Container instance.
     */
    protected static function getContainer(): Container
    {
        if (!isset(self::$container)) {
            self::$container = self::CreateContainer();
        }
        return self::$container;
    }

    /**
     * Creates a container for the 'TestWebShop' namespace with 'nl' as language.
     *
     * Override if the test needs another container.
     */
    protected static function CreateContainer(): Container
    {
        return new Container('TestWebShop', 'nl');
    }

    abstract protected function getTestsPath(): string;

    protected function getDataPath(): string
    {
        $shopNamespace = self::getContainer()->getShopNamespace();
        return $this->getTestsPath() . "/Integration/$shopNamespace/Data";
    }

    /**
     * @beforeClass Adds translations that are not added by default when the Translator is
     *   created.
     */
    public static function addTranslations(): void
    {
        self::getContainer()->getTranslator()->add(new Translations());
    }

    /**
     * Returns a timing string.
     *
     * @param string $location
     *   Will be printed along with the tine, to indicate where in the code the timing was
     *   taken.
     * @param bool $doEcho
     *   Whether the resulting string should also be echoed to the output.
     *
     * @return string
     *   The time (with microseconds) and location.
     */
    protected static function eTime(string $location = '', bool $doEcho = true): string
    {
        $line = self::getTime() . ' ' . $location . PHP_EOL;
        if ($doEcho) {
            echo $line;
        }
        return $line;
    }

    /**
     * Returns the current time with microseconds.
     */
    protected static function getTime(): string
    {
        return (new DateTimeImmutable())->format('H:i:s.u');
    }

    protected function getDiffInSeconds(DateTimeInterface $time1, DateTimeInterface $time2): int
    {
        $interval = $time1->diff($time2);
        return (int) (new DateTimeImmutable('@0'))->add($interval)->format('U');
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @throws \JsonException
     */
    protected function _testCreate(string $dataPath, string $type, int $id, array $excludeFields = []): void
    {
        $invoiceSource = self::getContainer()->createSource($type, $id);
        $invoiceAddResult = self::getContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoice = self::getContainer()->getInvoiceCreate()->create($invoiceSource, $invoiceAddResult);
        $result = $invoice->toArray();
        // Get order from Order{id}.json.
        $expected = $this->getTestSource($dataPath, $type, $id);
        if ($expected !== null) {
            // Save order to Order{id}.latest.json, so we can compare differences ourselves.
            $this->saveTestSource($dataPath, $type, $id, false, $result);
            static::assertCount(1, $result);
            static::assertArrayHasKey(Fld::Customer, $result);
            $messages = [];
            $this->compareAcumulusObjects($expected[Fld::Customer], $result[Fld::Customer], Fld::Customer, $excludeFields, $messages);
            /** @noinspection JsonEncodingApiUsageInspection  false positive */
            static::assertCount(0, $messages, json_encode($messages, Log::JsonFlags));
        } else {
            // File does not yet exist: first time for a new test order: save order to Order{id}.json.
            // Will raise a warning that no asserts have been executed.
            $this->saveTestSource($dataPath, $type, $id, true, $result);
        }
    }

    /**
     * Asserts that 2 Acumulus objects are equal.
     *
     * @param array $expected
     *   json encoded, and converted to an associative array, representation of the 1st
     *   object.
     * @param array $object
     *   json encoded, and converted to an associative array, representation of the 2nd
     *   object.
     * @param string $objectName
     *   The name of the (sub)object, e.g. 'invoice', or 'line'.
     * @param array $excludeFields
     *   A, possibly empty, list of fields to exclude in the comparison.
     */
    protected function compareAcumulusObjects(
        array $expected,
        array $object,
        string $objectName,
        array $excludeFields,
        array &$messages
    ):
    void {
        foreach ($expected as $field => $value) {
            try {
                if (!in_array($field, $excludeFields, true)) {
                    static::assertArrayHasKey($field, $object, $objectName);
                    switch ($field) {
                        case 'invoice':
                        case 'emailAsPdf':
                            $this->compareAcumulusObjects($value, $object[$field], "$objectName::$field", $excludeFields, $messages);
                            break;
                        case 'line':
                            foreach ($value as $index => $line) {
                                static::assertArrayHasKey($index, $object[$field], "$objectName::{$field}[$index]");
                                $this->compareAcumulusObjects(
                                    $line,
                                    $object[$field][$index],
                                    "$objectName::{$field}[$index]",
                                    $excludeFields,
                                    $messages
                                );
                            }
                            break;
                        default:
                            if (is_float($value)) {
                                static::assertEqualsWithDelta($value, $object[$field], 0.005, "$objectName::$field");
                            } else {
                                static::assertSame($value, $object[$field], "$objectName::$field");
                            }
                            break;
                    }
                }
            } catch (ExpectationFailedException $e) {
                $messages[] = $e->getMessage();
            }
        }
    }

    /**
     * Returns test data, typically a created and completed invoice converted to an array.
     *
     * @return mixed|null
     *   The json decoded testdata, or null if the file does not yet exist.
     *
     * @throws \JsonException
     */
    protected function getTestSource(string $path, string $type, int $id): mixed
    {
        $filename = "$path/$type$id.json";
        if (!is_readable($filename)) {
            return null;
        }
        $json = file_get_contents($filename);
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Saves test data, typically a created and completed invoice converted to an array.
     *
     * @param mixed $data
     *   The data to be saved (in json format).
     */
    protected function saveTestSource(string $path, string $type, int $id, bool $isNew, mixed $data): void
    {
        $append = $isNew ? '' : '.latest';
        $filename = "$path/$type$id$append.json";
        /** @noinspection JsonEncodingApiUsageInspection  false positive */
        file_put_contents($filename, json_encode($data, Log::JsonFlags) . "\n");
    }

    public function copyLatestTestSources(): void
    {
        $path = $this->getDataPath();
        $files = glob("$path/*.latest.json");
        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            preg_match('/([A-Za-z]+)(\d+)/', $fileName, $matches);
            $type = $matches[1];
            $id = (int) $matches[2];
            $this->copyLatestTestSource($path, $type, $id);
        }
    }

    /**
     * Copies latest test data to test data to check against.
     *
     * Refactorings should not alter the resulting array contents but may alter the order
     * after converting to json. To be able to show real differences not just line order
     * changes, it may come handy to copy the .latest file to the file to check against,
     * BUT ONLY IF THE TESTS PASSED!
     */
    protected function copyLatestTestSource(string $path, string $type, int $id): void
    {

        $append = '.latest';
        $sourceFilename = "$path/$type$id$append.json";
        $targetFilename = "$path/$type$id.json";
        file_put_contents($targetFilename, file_get_contents($sourceFilename));
    }

    public function updateTestSources(): void
    {
        $path = $this->getDataPath();
        $files = glob("$path/*.json");
        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            if (!str_contains($fileName, '.')) {
                $this->updateTestSource($file);
            }
        }
    }

    public function updateTestSource(string $file): void
    {
        $replacements = [
            // PHP doesn't know '$1"\L$2": '
            '/( +)"([a-zA-Z0-9]+)": /' => function (array $matches) {
                return strtolower($matches[0]);
            },
            // PHP doesn't know '$1\L$2$4'
            '/(\'|")(unitPrice(Inc)?)(\'|")/' => function (array $matches) {
                return strtolower($matches[0]);
            },
            '/"altMeta/' => '"altmeta',
            '/vat(-t|T)ype/' => 'vattype',
            '/vat(-r|R)ate/' => 'vatrate',
            '/vat(-a|A)mount/' => 'vatamount',
            '/email-as-pdf/' => 'emailaspdf',
        ];

        $contents = file_get_contents($file);
        foreach ($replacements as $regExpSearch => $regExpReplace) {
            if (is_string($regExpReplace)) {
                $contents = preg_replace($regExpSearch, $regExpReplace, $contents);
            } else {
                $contents = preg_replace_callback($regExpSearch, $regExpReplace, $contents);
            }
        }
        file_put_contents($file, $contents);
    }

    /**
     * Saves test html data, typically a rendered form.
     *
     * @param string $data
     *   The html string to be saved.
     */
    protected function saveTestHtml(string $path, string $name, string $data): void
    {
        $append = 'latest';
        $filename = "$path/$name.html";
        if (file_exists($filename)) {
            $filename = "$path/$name.$append.html";
        }
        $data = static::getContainer()->getUtil()->maskHtml($data);
        file_put_contents($filename, static::$htmlStart . $data . static::$htmlEnd);
    }

    /**
     * Returns a test mail.
     */
    protected function getTestMail(string $path, string $name): ?array
    {
        $mail = null;
        $filename = "$path/$name.mail";
        if (is_readable($filename)) {
            eval('$mail = ' . file_get_contents($filename) . ';');
        }
        /** @noinspection PhpExpressionAlwaysNullInspection */
        return $mail;
    }

    /**
     * Saves test mail data.
     *
     * @param array $data
     *   The mail data to be saved (in json format).
     */
    protected function saveTestMail(string $path, string $name, array $data): void
    {
        $append = 'latest';
        $filename = "$path/$name.mail";
        if (file_exists($filename)) {
            $filename = "$path/$name.$append.mail";
        }
        file_put_contents($filename, var_export($data, true) . "\n");
    }
}
