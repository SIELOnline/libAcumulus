<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Helpers;


use Siel\Acumulus\OpenCart\Helpers\Registry as BaseRegistry;

/**
 * {@inheritdoc}
 */
class Registry extends BaseRegistry
{
    /**
     * Returns the location of the extension's files.
     *
     * @return string
     *   The location of the extension's files.
     */
    public function getLocation()
    {
        return 'module/acumulus';
    }

    /**
     * Returns a link to the given route.
     *
     * @param string $route
     *
     * @return string
     *   The link to the given route, including standard arguments.
     */
    public function getLink($route)
    {
        $token = 'token';
        $secure = 'SSL';
        return $this->url->link($route, $token . '=' . $this->session->data[$token], $secure);
    }
}
