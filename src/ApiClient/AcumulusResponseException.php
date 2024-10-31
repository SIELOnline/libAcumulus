<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use Throwable;

/**
 * Class AcumulusResponseException represents errors based on inspecting the
 * response of an Acumulus API request.
 *
 * The message will be constructed based on the $body; HTTP status $code; and,
 * if passed, the $additionalInfo.
 */
class AcumulusResponseException extends AcumulusException
{
    /**
     * @param string|Throwable $additionalInfo
     */
    public function __construct(string $body, int $code, $additionalInfo = '')
    {
        $message = "HTTP status code=$code";
        $message .= $this->getAdditionalInfo($additionalInfo);
        $message .= "\nHTTP body=$body";
        parent::__construct($message, $code, $additionalInfo instanceof Throwable ? $additionalInfo : null);
    }

    private function getAdditionalInfo(Throwable|string $additionalInfo): string
    {
        $result = '';
        if ($additionalInfo instanceof Throwable) {
            $additionalInfo = $additionalInfo->getMessage();
        }
        if (!empty($additionalInfo)) {
            $result = " $additionalInfo";
        }
        return $result;
    }
}
