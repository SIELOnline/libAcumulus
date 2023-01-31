<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use function strlen;

/**
 * OC4 specific Registry code.
 */
class Registry extends \Siel\Acumulus\OpenCart\Helpers\Registry
{
    /**
     * {@inheritDoc}
     */
    public function getRoute(string $method, string $extension = 'acumulus', string $extensionType = 'module'): string
    {
        if ($extension === '') {
            // OpenCart core controller action: use unchanged.
            $route = $method;
        } else {
            $route = "extension/$extension/$extensionType/$extension";
            if ($method !== '') {
                $route .= '|' . $method;
            }
        }
        return $route;
    }

    /**
     * {@inheritDoc}
     */
    public function getLoadRoute(string $object = '', string $extension = 'acumulus', string $extensionType = 'module'): string
    {
        return "extension/$extension/$extensionType/$object";
    }

    /**
     * {@inheritDoc}
     */
    public function getFileUrl(string $file = '', string $extension = 'acumulus'): string
    {
        return HTTP_CATALOG . substr(DIR_EXTENSION, strlen(DIR_OPENCART)) . $extension . '/' . strtolower(APPLICATION) . '/' . $file;
    }

    /**
     * {@inheritDoc}
     */
    protected function inAdmin(): bool
    {
        return $this->config->get('application') === 'Admin';
    }
}
