<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests;

use Siel\Acumulus\Tests\Utils\Base;
use Siel\Acumulus\Tests\Utils\Form;
use Siel\Acumulus\Tests\Utils\Invoice;
use Siel\Acumulus\Tests\Utils\Log;
use Siel\Acumulus\Tests\Utils\Mail;
use Siel\Acumulus\Tests\Utils\Time;

/**
 * AcumulusTestUtils contains test utilities for the various shop-specific test
 * environments.
 */
trait AcumulusTestUtils
{
    use Base;
    use Time;
    use Invoice;
    use Mail;
    use Log;
    use Form;
}
