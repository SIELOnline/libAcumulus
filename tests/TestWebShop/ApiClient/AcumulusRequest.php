<?php

namespace Siel\Acumulus\Tests\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\AcumulusRequest as BaseAcumulusRequest;

class AcumulusRequest extends BaseAcumulusRequest
{
    protected function getBasicSubmit(bool $needContract): array
    {
        $result = parent::getBasicSubmit($needContract);
        if (strpos($this->uri, 'entry/noemailonerror') !== false) {
            $this->uri = str_replace('entry/noemailonerror', 'entry/entry_info', $this->uri);
            unset($result['contract']['emailonerror']);
            unset($result['contract']['emailonwarning']);
        }
        if (strpos($this->uri, 'entry/noemailonwarning') !== false) {
            $this->uri = str_replace('entry/noemailonwarning', 'entry/entry_info', $this->uri);
            unset($result['contract']['emailonwarning']);
        }
        return $result;
    }

    protected function getCurlOptions(): array
    {
        $options =  parent::getCurlOptions();
        if (strpos($this->uri, 'entry/timeout') !== false) {
            $this->uri = str_replace('entry/timeout', 'entry/entry_info', $this->uri);
            $options[CURLOPT_CONNECTTIMEOUT_MS] = 1;
            $options[CURLOPT_TIMEOUT_MS] = 1;
        }
        return $options;
    }
}
