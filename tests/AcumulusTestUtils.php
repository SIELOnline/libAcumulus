<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests;

use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\ExpectationFailedException;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Translations;

use function in_array;
use function is_float;

/**
 * AcumulusTestUtils contains test functionalities for the various shop specific test
 * environments.
 */
trait AcumulusTestUtils
{
    /**
     * Returns an Acumulus Container instance.
     */
    abstract protected static function getAcumulusContainer(): Container;

    /**
     * @beforeClass Adds translations that are not added by default when the Translator is
     *   created.
     */
    public static function addTranslations(): void
    {
        self::getAcumulusContainer()->getTranslator()->add(new Translations());
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
        return (new DateTime())->format('H:i:s.u');
    }

    protected function getDiffInSeconds(DateTimeInterface $time1, DateTimeInterface $time2): int
    {
        $interval = $time1->diff($time2);
        return (int) (new DateTime('@0'))->add($interval)->format('U');
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @throws \JsonException
     */
    protected function _testCreate(string $dataPath, string $type, int $id, array $excludeFields = []): void
    {
        $invoiceSource = self::getAcumulusContainer()->createSource($type, $id);
        $invoiceAddResult = self::getAcumulusContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoice = self::getAcumulusContainer()->getInvoiceCreate()->create($invoiceSource, $invoiceAddResult);
        $result = $invoice->toArray();
        // Get order from Order{id}.json.
        $expected = $this->getTestSource($dataPath, $type, $id);
        if ($expected !== null) {
            // Save order to Order{id}.latest.json, so we can compare differences ourselves.
            $this->saveTestSource($dataPath, $type, $id, false, $result);
            $this->assertCount(1, $result);
            $this->assertArrayHasKey(Fld::Customer, $result);
            $messages = [];
            $this->compareAcumulusObjects($expected[Fld::Customer], $result[Fld::Customer], Fld::Customer, $excludeFields, $messages);
            /** @noinspection JsonEncodingApiUsageInspection  false positive */
            $this->assertCount(0, $messages, json_encode($messages, Log::JsonFlags));
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
                    $this->assertArrayHasKey($field, $object, $objectName);
                    switch ($field) {
                        case 'invoice':
                        case 'emailAsPdf':
                            $this->compareAcumulusObjects($value, $object[$field], "$objectName::$field", $excludeFields, $messages);
                            break;
                        case 'line':
                            foreach ($value as $index => $line) {
                                $this->assertArrayHasKey($index, $object[$field], "$objectName::{$field}[$index]");
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
                                $this->assertEqualsWithDelta($value, $object[$field], 0.005, "$objectName::$field");
                            } else {
                                $this->assertSame($value, $object[$field], "$objectName::$field");
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
    protected function getTestSource(string $path, string $type, int $id)
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
    protected function saveTestSource(string $path, string $type, int $id, bool $isNew, $data): void
    {
        $append = $isNew ? '' : '.latest';
        $filename = "$path/$type$id$append.json";
        /** @noinspection JsonEncodingApiUsageInspection  false positive */
        file_put_contents($filename, json_encode($data, Log::JsonFlags) . "\n");
    }
}
