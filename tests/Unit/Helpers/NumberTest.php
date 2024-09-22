<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

/**
 * Test for the {@see Creator} class.
 */
class NumberTest extends TestCase
{

    public function testGetVatRangeTags(): void
    {
        $test1 = Number::getDivisionRange(7.18378, 34.22, 0.0001, 0.0001);
        $this->assertLessThanOrEqual(0.21, $test1['min']);
        $this->assertGreaterThanOrEqual(0.20, $test1['max']);

        $test2 = Number::getDivisionRange(7.18378, 34.22, 0.001, 0.001);
        $this->assertLessThanOrEqual(0.21, $test2['min']);
        $this->assertGreaterThanOrEqual(0.20, $test2['max']);

        $test3 = Number::getDivisionRange(7.18378, 34.22, 0.005, 0.005);
        $this->assertLessThanOrEqual(0.21, $test3['min']);
        $this->assertGreaterThanOrEqual(0.21, $test3['max']);

        $test4 = Number::getDivisionRange(7.18378, 34.22, 0.01, 0.01);
        $this->assertLessThanOrEqual(0.21, $test4['min']);
        $this->assertGreaterThanOrEqual(0.21, $test4['max']);
    }

}
