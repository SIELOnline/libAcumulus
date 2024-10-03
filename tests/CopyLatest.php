<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests;

use Siel\Acumulus\Helpers\Container;

use function strlen;

/**
 * CopyLatest copies the .latest files to their name without .latest, overwriting
 * existing contents.
 */
class CopyLatest
{
    use AcumulusTestUtils;

    protected static function getAcumulusContainer(): Container
    {
        throw new \RuntimeException('Not yet implemented');
    }

    public function run(string $path): void
    {
        $files = glob("$path/*.latest.json");
        foreach ($files as $file) {
            $parts = pathinfo($file);
            $invoiceSource = substr(pathinfo($file)['filename'], 0, -strlen('.latest'));
            preg_match('/([A-Za-z]+)(\d+)/', $parts['filename'], $matches);
            $type = $matches[1];
            $id = (int) $matches[2];
            $this->copyLatestTestSource($path, $type, $id);
        }
    }
}
