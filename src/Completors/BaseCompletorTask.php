<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Translator;

/**
 * BaseCompletor implements acts as a base class for completing tasks.
 *
 * Via the constructor it gets access to the Configuration and Container.
 */
abstract class BaseCompletorTask implements CompletorTaskInterface
{
    private Container $container;
    private Config $config;
    private Translator $translator;

    public function __construct(Container $container, Config $config, Translator $translator)
    {
        $this->container = $container;
        $this->config = $config;
        $this->translator = $translator;
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Returns the configured value for this setting.
     *
     * @return mixed
     *   The configured value for this setting, or null if not set, and no default is
     *   available. Our {@see \Siel\Acumulus\Config\Config configuration} knows the types:
     *   string, int, float, bool and array.
     */
    protected function configGet(string $key): mixed
    {
        return $this->config->get($key);
    }

    abstract public function complete(AcumulusObject $acumulusObject, ...$args): void;
}
