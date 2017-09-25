<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\OpenCart23\Helpers;

use Siel\Acumulus\OpenCart\Helpers\Registry as BaseRegistry;

/**
 * {@inheritdoc}
 */
class Registry extends BaseRegistry
{
    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Returns the extension model that can be used to retrieve payment methods.
     *
     * @return \ModelExtensionExtension
     *
     * @throws \Exception
     */
    public function getExtensionModel()
    {
        if ($this->extensionModel === null) {
            $this->load->model('extension/extension');
            /** @noinspection PhpUndefinedFieldInspection */
            $this->extensionModel = $this->model_extension_extension;
        }
        return $this->extensionModel;
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Returns the extension model that can be used to retrieve payment methods.
     *
     * @return \ModelExtensionEvent
     *
     * @throws \Exception
     */
    public function getEventModel()
    {
        if ($this->eventModel === null) {
            $this->load->model('extension/event');
            /** @noinspection PhpUndefinedFieldInspection */
            $this->eventModel = $this->model_extension_event;
        }
        return $this->eventModel;
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
        $secure = true;
        return $this->url->link($route, $token . '=' . $this->session->data[$token], $secure);
    }
}
