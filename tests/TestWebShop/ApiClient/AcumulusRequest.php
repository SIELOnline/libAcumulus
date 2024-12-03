<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\AcumulusRequest as BaseAcumulusRequest;
use Siel\Acumulus\Data\BasicSubmit;

/**
 * AcumulusRequest extends the real AcumulusRequest with testing functionality.
 */
class AcumulusRequest extends BaseAcumulusRequest
{
    protected function getBasicSubmit(): BasicSubmit
    {
        $result = parent::getBasicSubmit();
        if (str_contains($this->uri, 'entry/noemailonerror')) {
            $this->uri = str_replace('entry/noemailonerror', 'entry/entry_info', $this->uri);
            unset($result->getContract()->emailOnError, $result->getContract()->emailOnWarning);
        }
        if (str_contains($this->uri, 'entry/noemailonwarning')) {
            $this->uri = str_replace('entry/noemailonwarning', 'entry/entry_info', $this->uri);
            unset($result->getContract()->emailOnWarning);
        }
        return $result;
    }

    protected function getCurlOptions(): array
    {
        $options =  parent::getCurlOptions();
        if (str_contains($this->uri, 'entry/timeout')) {
            $this->uri = str_replace('entry/timeout', 'entry/entry_info', $this->uri);
            $options[CURLOPT_CONNECTTIMEOUT_MS] = 1;
            $options[CURLOPT_TIMEOUT_MS] = 1;
        }
        return $options;
    }
}
