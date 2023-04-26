<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $invoice may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Translator;

/**
 * CustomerCompletor completes an {@see \Siel\Acumulus\Data\Customer}.
 *
 * After an invoice has been collected, the shop specific part, it needs to be
 * completed, also the customer part. Think of things like:
 * - Adding customer type based on a setting.
 * - Anonymising data.
 */
abstract class BaseCompletor
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

    protected function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Returns the configured value for this setting.
     *
     * @return mixed|null
     *   The configured value for this setting, or null if not set and no
     *   default is available.
     */
    protected function configGet(string $key)
    {
        return $this->config->get($key);
    }

    /**
     * Returns a {@see \Siel\Acumulus\Completors\CompletorTaskInterface}.
     *
     * @param string $dataType
     *   The data type it operates on. One of the
     *   {@see \Siel\Acumulus\Data\DataType} constants. This is used as a
     *   sub namespace when constructing the class name to load.
     * @param string $task
     *   The task to be executed. This is used to construct the class name of a
     *   class that performs the given task and implements
     *   @see \Siel\Acumulus\Completors\CompletorTaskInterface}. Only the task
     *   name should be provided, not the namespace, nor the 'Complete' at the
     *   beginning.
     */
    public function getCompletorTask(string $dataType, string $task): CompletorTaskInterface
    {
        return $this->getContainer()->getCompletorTask($dataType, $task);
    }

    /**
     * Completes an {@see \Siel\Acumulus\Data\Customer}.
     *
     * This phase is executed after the collecting phase.
     */
    abstract public function complete(AcumulusObject $acumulusObject): void;
}
