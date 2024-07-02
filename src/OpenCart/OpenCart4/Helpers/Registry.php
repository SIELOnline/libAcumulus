<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use function strlen;

/**
 * OC4 specific Registry code.
 */
class Registry extends \Siel\Acumulus\OpenCart\Helpers\Registry
{
    public function getRoute(string $method, string $extension = 'acumulus', string $extensionType = 'module'): string
    {
        if ($extensionType === '') {
            // OpenCart core controller action.
            $route = "$extension/$method";
        } else {
            // One of our own routes:
            $route = "extension/$extension/$extensionType/$extension";
            if ($method !== '') {
                $route .= '|' . $method;
            }
        }
        return $route;
    }

    public function getLoadRoute(string $object = '', string $extension = 'acumulus', string $extensionType = 'module'): string
    {
        return "extension/$extension/$extensionType/$object";
    }

    public function getAcumulusTrigger(string $trigger, string $moment): string
    {
        $extension = 'acumulus';
        $extensionType = 'module';
        if ($moment !== '') {
            $moment = '/' . $moment;
        }
        return "system/extension/$extensionType/$extension/$trigger$moment";
    }

    public function getFileUrl(string $file = '', string $extension = 'acumulus'): string
    {
        return HTTP_CATALOG . substr(DIR_EXTENSION, strlen(DIR_OPENCART)) . $extension . '/' . strtolower(APPLICATION) . '/' . $file;
    }

    protected function inAdmin(): bool
    {
        return $this->config->get('application') === 'Admin';
    }
}
