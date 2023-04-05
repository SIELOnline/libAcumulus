<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit;

/**
 * GetData provides test data to test Field and Collectors.
 */
class GetTestData
{
    private string $dataFile;

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/data.json';
    }

    /**
     * @throws \JsonException
     */
    public function load(): object
    {
        return json_decode(file_get_contents($this->dataFile), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \JsonException
     */
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
