<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Exception;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\ObjectManagerInterface;

/**
 * Registry is a wrapper around the Magento2 ObjectManager to get objects that
 * in Magento code would be injected via the constructor.
 */
class Registry
{
    protected static Registry $instance;

    /**
     * Returns the Registry instance.
     */
    public static function getInstance(): Registry
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Returns the object manager
     */
    protected function getObjectManager(): ObjectManagerInterface
    {
        /** @var ObjectManagerInterface $objectManager */
        static $objectManager;

        if (!isset($objectManager)) {
            /** @var \Magento\Framework\App\Bootstrap $bootstrap */
            global $bootstrap;

            if ($bootstrap) {
                $localBootstrap = $bootstrap;
            } else {
                $pos = strpos(__DIR__, str_replace('/', DIRECTORY_SEPARATOR, '/vendor/siel/acumulus/src/Magento/Helpers'));
                $root = substr(__DIR__, 0, $pos);
                $localBootstrap = Bootstrap::create($root, $_SERVER);
            }
            $objectManager = $localBootstrap->getObjectManager();
        }
        return $objectManager;
    }

    /**
     * Creates a new object of the given type.
     */
    public function create(string $type)
    {
        return $this->getObjectManager()->create($type);
    }

    /**
     * Retrieves a cached object instance or creates a new instance.
     */
    public function get(string $type)
    {
        return $this->getObjectManager()->get($type);
    }

    /**
     * @return string
     *   The locale code.
     */
    public function getLocale(): string
    {
        /** @var \Magento\Framework\Locale\ResolverInterface $resolver */
        $resolver = $this->get(ResolverInterface::class);
        return $resolver->getLocale();
    }

    /**
     * Returns the composer version for the given module.
     */
    public function getModuleVersion(string $moduleName): string
    {
        try {
            /** @var ComponentRegistrarInterface $registrar */
            $registrar = $this->get(ComponentRegistrarInterface::class);
            $path = $registrar->getPath(ComponentRegistrar::MODULE, $moduleName);
            if ($path) {
                /** @var ReadFactory $readFactory */
                $readFactory = $this->get(ReadFactory::class);
                $directoryRead = $readFactory->create($path);
                $composerJsonData = $directoryRead->readFile('composer.json');
                // @todo: json error handling: switch to throw.
                $data = json_decode($composerJsonData, false);
                if ($data !== null) {
                    if (!empty($data->version)) {
                        $result = $data->version;
                    } else {
                        $result = 'NOT SET';
                    }
                } else {
                    $result = 'JSON ERROR';
                }
            } else {
                $result = 'MODULE ERROR';
            }
        } catch (Exception $e) {
            // FileSystemException or a ValidatorException
            $result = $e->getMessage();
        }

        return $result;
    }

    /**
     * Returns the schema version for the given module.
     *
     * @param string $moduleName
     *
     * @return string|false
     */
    public function getSchemaVersion(string $moduleName)
    {
        /** @var ResourceInterface $resource */
        $resource = $this->get(ResourceInterface::class);
        return $resource->getDataVersion($moduleName);
    }
}
