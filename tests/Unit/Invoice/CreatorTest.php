<?php
namespace Siel\Acumulus\Unit\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Invoice\Creator;
use Siel\Acumulus\Meta;

class CreatorTest extends TestCase
{

    public function testGetVatRangeTags()
    {
        $test1 = Creator::getVatRangeTags(7.18378, 34.22, $numeratorPrecision = 0.0001, $denominatorPrecision = 0.0001);
        $this->assertLessThanOrEqual(21, $test1[Meta::VatRateMin]);
        $this->assertGreaterThanOrEqual(20, $test1[Meta::VatRateMax]);

        $test2 = Creator::getVatRangeTags(7.18378, 34.22, $numeratorPrecision = 0.001, $denominatorPrecision = 0.001);
        $this->assertLessThanOrEqual(21, $test2[Meta::VatRateMin]);
        $this->assertGreaterThanOrEqual(20, $test2[Meta::VatRateMax]);

        $test3 = Creator::getVatRangeTags(7.18378, 34.22, $numeratorPrecision = 0.005, $denominatorPrecision = 0.005);
        $this->assertLessThanOrEqual(21, $test3[Meta::VatRateMin]);
        $this->assertGreaterThanOrEqual(21, $test3[Meta::VatRateMax]);

        $test4 = Creator::getVatRangeTags(7.18378, 34.22, $numeratorPrecision = 0.01, $denominatorPrecision = 0.01);
        $this->assertLessThanOrEqual(21, $test4[Meta::VatRateMin]);
        $this->assertGreaterThanOrEqual(21, $test4[Meta::VatRateMax]);
    }

}
