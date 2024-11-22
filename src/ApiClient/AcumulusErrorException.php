<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use Siel\Acumulus\Helpers\Message;

/**
 * Class AcumulusErrorException represents Acumulus API responses that contain a
 * non-empty <error> section.
 */
class AcumulusErrorException extends AcumulusException
{
    /**
     * @param \Siel\Acumulus\ApiClient\AcumulusResult $result
     *   A result with hasError().
     */
    public function __construct(AcumulusResult $result)
    {
        parent::__construct($result->formatMessages(Message::Format_PlainList));
    }
}
