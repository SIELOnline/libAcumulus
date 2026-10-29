<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Data;

use Siel\Acumulus\Helpers\Util;

/**
 * GetData provides test data to test Field and Collectors.
 */
class GetTestData
{
    public function getJson(bool $decode = true): array|string
    {
        $data = file_get_contents(__DIR__ . '/data.json');
        if ($decode) {
            $data = (new Util())->convertJsonToArray($data);
            $data['order']['customer'] = $data['customer'];
            $data['invoiceSource'] = $data['order'];
        }
        return $data;
    }

    public function getHtml(string $fileName = __DIR__ . '/Form/testForm.html'): string
    {
        return file_get_contents($fileName);
    }
}
