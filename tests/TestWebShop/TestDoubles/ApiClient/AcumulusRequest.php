<?php
namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\ApiClient\AcumulusRequest as BaseAcumulusRequest;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Unit\ApiClient\ApiRequestResponseExamples;

/**
 * Communicator implements the communication with the Acumulus web API.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - Communicating with the Acumulus webservice using the
 *   {@se HttpCommunicator}.
 * - Good error handling, including detecting html responses from the proxy
 *   before the actual web service.
 */
class AcumulusRequest extends BaseAcumulusRequest
{
    /**
     * @var \Siel\Acumulus\Unit\ApiClient\ApiRequestResponseExamples
     */
    private $examples;

    public function __construct(Container $container, Config $config, string $userLanguage)
    {
        parent::__construct($container, $config, $userLanguage);
        $this->examples = new ApiRequestResponseExamples();
    }

    protected function getBasicSubmit(bool $needContract): array
    {
        return $this->examples->getBasicSubmit($needContract);
    }
}
