<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\ApiClient\AcumulusRequest as BaseAcumulusRequest;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Util;
use Siel\Acumulus\Tests\Unit\ApiClient\ApiRequestResponseExamples;

/**
 * The AcumulusRequest test double overrules the real AcumulusRequest class by injecting
 * example message structures
 */
class AcumulusRequest extends BaseAcumulusRequest
{
    private ApiRequestResponseExamples $examples;

    public function __construct(Container $container, Config $config, Environment $environment, Util $util, string
    $userLanguage)
    {
        parent::__construct($container, $config, $environment, $util, $userLanguage);
        $this->examples = new ApiRequestResponseExamples();
    }

    protected function getBasicSubmit(bool $needContract): array
    {
        return $this->examples->getBasicSubmit($needContract);
    }

    public function submitToArray($submit): array
    {
        return parent::submitToArray($submit);
    }

}
