<?php

namespace Siel\Acumulus\TestWebShop\ApiClient;

class AcumulusRequest extends \Siel\Acumulus\ApiClient\AcumulusRequest
{
    protected function getCurlOptions(): array
    {
        $options =  parent::getCurlOptions();
        if (strpos($this->uri, 'entry/timeout') !== false) {
            $this->uri = str_replace('', 'entry/entry_info', $this->uri);
            $options[CURLOPT_CONNECTTIMEOUT_MS] = 1;
            $options[CURLOPT_TIMEOUT_MS] = 1;
        }
        return $options;
    }
}
