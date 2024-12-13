<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Data;

/**
 * GetData provides test data to test Field and Collectors.
 */
class GetTestData
{
    public function getJson(bool $decode = true): mixed
    {
        $data = file_get_contents(__DIR__ . '/data.json');
        if ($decode) {
            $data = json_decode($data, false, 512, JSON_THROW_ON_ERROR);
            $data->order->customer = $data->customer;
            $data->invoiceSource = $data->order;
        }
        return $data;
    }

    public function getHtml(string $fileName = __DIR__ . '/Form/testForm.html'): string
    {
        return file_get_contents($fileName);
    }
}
