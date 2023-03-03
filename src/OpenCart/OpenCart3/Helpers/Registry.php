<?php
/**
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use function defined;
use function strlen;

/**
 * OC3 specific Registry code.
 */
class Registry extends \Siel\Acumulus\OpenCart\Helpers\Registry
{
    public function getRoute(string $method, string $extension = 'acumulus', string $extensionType = 'module'): string
    {
        if ($extension === '') {
            // OpenCart core controller action: use unchanged.
            $route = $method;
        } else {
            $route = "extension/$extensionType/$extension";
            if ($method !== '') {
                $route .= "/$method";
            }
        }
        return $route;
    }

    public function getLoadRoute(string $object = '', string $extension = 'acumulus', string $extensionType = 'module'): string
    {
        return "extension/$extensionType/$object";
    }

    public function getAcumulusTrigger(string $trigger, string $moment): string
    {
        if ($moment !== '') {
            $moment = "/$moment";
        }
        return $this->getRoute("$trigger$moment");
    }

    public function getFileUrl(string $file = '', string $extension = 'acumulus'): string
    {
        return (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . $file;
    }

    protected function inAdmin(): bool
    {
        return strrpos(DIR_APPLICATION, '/admin/') === strlen(DIR_APPLICATION) - strlen('/admin/');
    }
}
