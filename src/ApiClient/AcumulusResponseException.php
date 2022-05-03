<?php
namespace Siel\Acumulus\ApiClient;

use Exception;
use Throwable;

/**
 * Class AcumulusResponseException represents errors based on inspecting the
 * response on an Acumulus API request.
 *
 * The message will be constructed based on the $body, http status $code, and,
 * if passed, the $additionalInfo.
 */
class AcumulusResponseException extends AcumulusException
{
    /**
     * @param string $body
     * @param int $code
     * @param string|\Throwable $additionalInfo
     */
    public function __construct(string $body, int $code, $additionalInfo = '')
    {
        $message = "HTTP status code=$code";
        $message .= $this->getAdditionalInfo($additionalInfo);
        $message .= "\nHTTP body=$body";
        parent::__construct($message, $code, $additionalInfo instanceof Throwable ? $additionalInfo: null);
    }

    /**
     * @param string|\Throwable $additionalInfo
     *
     * @return string
     */
    private function getAdditionalInfo($additionalInfo): string
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
