<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests;

use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\Path;
use Siel\Acumulus\Tests\Utils\Form;
use Siel\Acumulus\Tests\Utils\Invoice;
use Siel\Acumulus\Tests\Utils\Log;
use Siel\Acumulus\Tests\Utils\Mail;
use Siel\Acumulus\Tests\Utils\Time;

/**
 * AcumulusTestUtils contains test utilities for library and shop tests.
 */
trait AcumulusTestUtils
{
    use AcumulusContainer;
    use Path;
    use Time;
    use Invoice;
    use Mail;
    use Log;
    use Form;
}
