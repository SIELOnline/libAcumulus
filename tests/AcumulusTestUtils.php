<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests;

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
    abstract protected function getAcumulusContainer(): Container;

    /**
     * @before Adds translations that are not added by default when the Translator is
     *   created.
     */
    protected function addTranslations(): void
    {
        $this->getAcumulusContainer()->getTranslator()->add(new Translations());
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
