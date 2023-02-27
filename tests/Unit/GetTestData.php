<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit;

use stdClass;

/**
 * GetData provides test data to test Field and Collectors.
 */
class GetTestData
{
    private string $configDir = __DIR__ . '/../../../config';
    private string $dataFile;

    public function __construct()
    {
        $this->dataFile = $this->configDir . '/data.json';
    }

    public function load(): object
    {
        return is_readable($this->dataFile) ? json_decode(file_get_contents($this->dataFile), false) : new stdClass();
    }

    public function get(): object
    {
        $data = $this->load();
        $data->customer->invoice_address = $data->invoice_address;
        unset($data->invoice_address);
        $data->customer->shipping_address = $data->shipping_address;
        unset($data->shipping_address);
        $data->order->customer = $data->customer;
        $data->invoiceSource = $data->order;
        return $data;
    }
}
