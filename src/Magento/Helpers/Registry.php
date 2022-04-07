<?php
namespace Siel\Acumulus\Magento\Helpers;

use Exception;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Module\ResourceInterface;

/**
 * Registry is a wrapper around the Magento2 ObjectManager to get objects of all
 * sorts.
 */
class Registry
{
    /**
     * @var \Siel\Acumulus\Magento\Helpers\Registry
     */
    protected static $instance;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var string
     */
    protected $locale;

    /**
     * Returns the Registry instance.
     */
    public static function getInstance(): Registry
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Registry constructor.
     */
    protected function __construct()
    {
        /** @var \Magento\Framework\App\Bootstrap */
        global $bootstrap;

        if ($bootstrap) {
            $localBootstrap = $bootstrap;
        } else {
            $pos = strpos(__DIR__, str_replace('/', DIRECTORY_SEPARATOR, '/app/code/Siel/Acumulus/Magento2/Helpers'));
            $root = substr(__DIR__, 0, $pos);
            $localBootstrap = Bootstrap::create($root, $_SERVER);
        }
        /** @var \Magento\Framework\ObjectManagerInterface */
        $this->objectManager = $localBootstrap->getObjectManager();
    }

    /**
     * Creates a new object of the given type.
     */
    public function create(string $type)
    {
        return $this->objectManager->create($type);
    }

    /**
     * Retrieves a cached object instance or creates a new instance.
     */
    public function get(string $type)
    {
        return $this->objectManager->get($type);
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
                $data = json_decode($composerJsonData);
                $result = $data === null ? 'JSON ERROR' : (!empty($data->version) ? $data->version : 'NOT SET');
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
