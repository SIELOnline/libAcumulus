<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests;

use DateTime;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Translations;

use function in_array;
use function is_float;

/**
 * CompareAcumulusObjects contains.
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
    protected function compareAcumulusObjects(array $expected, array $object, string $objectName, array $excludeFields): void
    {
        foreach ($expected as $field => $value) {
            if (!in_array($field, $excludeFields, true)) {
                $this->assertArrayHasKey($field, $object);
                switch ($field) {
                    case 'invoice':
                    case 'emailAsPdf':
                        $this->compareAcumulusObjects($value, $object[$field], $field, $excludeFields);
                        break;
                    case 'line':
                        foreach ($value as $index => $line) {
                            $this->compareAcumulusObjects($line, $object[$field][$index], $field, $excludeFields);
                        }
                        break;
                    default:
                        if (is_float($value)) {
                            $this->assertEqualsWithDelta($value, $object[$field], 0.005);
                        } else {
                            $this->assertSame($value, $object[$field], "$objectName::$field");
                        }
                        break;
                }
            }
        }
    }
}
