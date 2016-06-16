<?php
namespace Siel\Acumulus\Magento2\Helpers;

use Magento\Framework\App\ObjectManager;

/**
 * Registry is a wrapper around the Magento2 ObjectManager to get
 */
class Registry
{
    /**
     * @var \Siel\Acumulus\Magento2\Helpers\Registry
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
     *
     * @return \Siel\Acumulus\Magento2\Helpers\Registry
     */
    public static function getInstance()
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
        /** @var \Magento\Framework\ObjectManagerInterface */
        $this->objectManager = $bootstrap->getObjectManager();
    }

    /**
     * @return \Magento\Framework\ObjectManagerInterface
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * Creates a new object of the given type.
     *
     * @param string $type
     *
     * @return mixed
     *
     */
    public function create($type)
    {
        return $this->objectManager->create($type);
    }

    /**
     * Retrieves a cached object instance or creates a new instance.
     *
     * @param string $type
     *
     * @return mixed
     */
    public function get($type) {
        return $this->objectManager->get($type);
    }

    /**
     * @return \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private function getScopeConfig()
    {
        return $this->get('\Magento\Framework\App\Config\ScopeConfigInterface');
    }

    /**
     * @return \Magento\Config\Model\ResourceModel\Config
     */
    public function getResourceConfig()
    {
        return $this->get('\Magento\Config\Model\ResourceModel\Config');
    }

    /**
     * @return \Magento\Backend\App\ConfigInterface
     */
    public function getConfigInterface()
    {
        return $this->get('Magento\Backend\App\ConfigInterface');
    }

    /**
     * @return \Magento\Framework\Module\ResourceInterface
     */
    public function getModuleResource()
    {
        return $this->get('\Magento\Framework\Module\ResourceInterface');
    }

    /**
     * @return string
     *   The locale code.
     */
    public function getLocale()
    {
        /** @var \Magento\Framework\Locale\ResolverInterface $resolver */
        $resolver = $this->objectManager->get('Magento\Framework\Locale\ResolverInterface');
        return $resolver->getLocale();
    }
}
