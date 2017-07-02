<?php
namespace Siel\Acumulus\Helpers;

use ReflectionClass;

/**
 * Container implements the ContainerInterface to allow other classes to
 * easily get the correct derived classes of the base classes.
 */
class Container implements ContainerInterface
{
    /** @const string */
    const baseNamespace = '\\Siel\\Acumulus\\';

    /** @var string The namespace for the current shop. */
    protected $shopNamespace;

    /** @var string The namespace for customisations on top of the current shop. */
    protected $customNamespace;

    /** @var array */
    protected $instances;

    /** @var bool */
    protected $moduleSpecificTranslationsAdded = false;

    /** @var string The language to display texts in. */
    protected $language;

    /**
     * Constructor.
     *
     * @param string $shopNamespace
     * @param string $language
     *   A language or locale code, e.g. nl, nl-NL, or en-UK.
     */
    public function __construct($shopNamespace, $language = '')
    {
        $this->instances = array();
        $this->shopNamespace = static::baseNamespace . $shopNamespace;
        global $sielAcumulusCustomNamespace;
        $this->customNamespace = !empty($sielAcumulusCustomNamespace) ? $sielAcumulusCustomNamespace : '';
        $this->language = substr($language, 0, 2);
    }

    /**
     * Sets the language code.
     *
     * @param string $language
     *   A language or locale code, e.g. nl, nl-NL, or en-UK.
     *
     * @return $this
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomNamespace($customNamespace)
    {
        $this->customNamespace = $customNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslator()
    {
        /** @var \Siel\Acumulus\Helpers\Translator $translator */
        $translator = $this->getInstance('Translator', 'Helpers', array($this->language));
        if (!$this->moduleSpecificTranslationsAdded) {
            try {
                /** @var \Siel\Acumulus\Helpers\TranslationCollection $translations */
                $translations = $this->getInstance('ModuleSpecificTranslations', 'helpers');
                $translator->add($translations);
            } catch (\InvalidArgumentException $e) {}
            $this->moduleSpecificTranslationsAdded = true;
        }
        return $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getLog()
    {
        return $this->getInstance('Log', 'Helpers');
    }

    /**
     * {@inheritdoc}
     */
    public function getRequirements()
    {
        return $this->getInstance('Requirements', 'Helpers');
    }

    /**
     * {@inheritdoc}
     */
    public function getMailer()
    {
        return $this->getInstance('Mailer', 'Helpers', array($this->getConfig(), $this->getTranslator(), $this->getLog()));
    }

    /**
     * {@inheritdoc}
     */
    public function getToken()
    {
        return $this->getInstance('Token', 'Helpers', array($this->getLog()));
    }

    /**
     * {@inheritdoc}
     */
    public function getFormRenderer()
    {
        return $this->getInstance('FormRenderer', 'Helpers');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormMapper()
    {
        return $this->getInstance('FormMapper', 'Helpers', array($this->getLog()));
    }

    /**
     * {@inheritdoc}
     */
    public function getService()
    {
        return $this->getInstance('Service', 'Web', array($this->getCommunicator(), $this->getConfig(), $this->getTranslator()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCommunicator()
    {
        return $this->getInstance('Communicator', 'Web', array($this->getConfig(), $this->getLog(), $this->getTranslator()));
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($invoiceSourceType, $invoiceSourceOrId)
    {
        return $this->getInstance('Source', 'Invoice', array($invoiceSourceType, $invoiceSourceOrId), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoice()
    {
        return $this->getInstance('Invoice', 'Invoice', array(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCompletor()
    {
        return $this->getInstance('Completor', 'Invoice', array($this->getConfig(), $this->getCompletorInvoiceLines(), $this->getCompletorStrategyLines(), $this->getTranslator(), $this->getService()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCompletorInvoiceLines()
    {
        return $this->getInstance('CompletorInvoiceLines', 'Invoice', array($this->getConfig(), $this->getFlattenerInvoiceLines()));
    }

    /**
     * {@inheritdoc}
     */
    public function getFlattenerInvoiceLines()
    {
        return $this->getInstance('FlattenerInvoiceLines', 'Invoice', array($this->getConfig()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCompletorStrategyLines()
    {
        return $this->getInstance('CompletorStrategyLines', 'Invoice', array($this->getConfig(), $this->getTranslator()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCreator()
    {
        return $this->getInstance('Creator', 'Invoice', array($this->getConfig(), $this->getToken(), $this->getTranslator(), $this->getLog()));
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        static $is1stTime = true;

        $log = $this->getLog();
        $configStore = $this->getConfigStore();
        $result = $this->getInstance('Config', 'Config', array($configStore, $this->getShopCapabilities(), $this->getTranslator(), $log));
        if ($is1stTime) {
            $configStore->setConfig($result);
            $pluginSettings = $result->getPluginSettings();
            $environment = $result->getEnvironment();
            $log->setLogLevel($pluginSettings['logLevel']);
            $log->setLibraryVersion($environment['libraryVersion']);
            $configStore->setConfig($result);
            $is1stTime = false;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigStore()
    {
        return $this->getInstance('ConfigStore', 'Config');
    }

    /**
     * {@inheritdoc}
     */
    public function getShopCapabilities()
    {
        return $this->getInstance('ShopCapabilities', 'Config', array($this->getTranslator(), $this->shopNamespace, $this->getLog()));
    }

    /**
     * {@inheritdoc}
     */
    public function getManager()
    {
        return $this->getInstance('InvoiceManager', 'Shop', array($this));
    }

    /**
     * {@inheritdoc}
     */
    public function getAcumulusEntryModel()
    {
        return $this->getInstance('AcumulusEntryModel', 'Shop', array($this->getLog()));
    }

    /**
     * {@inheritdoc}
     */
    public function getForm($type)
    {
        $class = ucfirst($type);
        $arguments = array(
            $this->getTranslator(),
            $this->getShopCapabilities(),
        );
        switch (strtolower($type)) {
            case 'config':
                $arguments[] = $this->getConfig();
                $arguments[] = $this->getService();
                break;
            case 'advanced':
                $class = 'AdvancedConfig';
                $arguments[] = $this->getConfig();
                $arguments[] = $this->getService();
                break;
            case 'batch':
                $arguments[] = $this->getManager();
                break;
        }
        return $this->getInstance($class . 'Form', 'Shop', $arguments);
    }

    /**
     * Returns an instance of the given class.
     *
     * The class is looked for in multiple namespaces, starting with the
     * $customNameSpace properties, continuing with the $shopNamespace property
     * and finally the base namespace (\Siel\Acumulus).
     *
     * Normally, only 1 instance is created per class but the $newInstance
     * argument can be used to change this behavior.
     *
     * @param string $class
     *   The name of the class without namespace. The class is searched for in
     *   multiple namespaces, see above.
     * @param string $subNamespace
     *   The sub namespace (within the namespaces tried) in which the class
     *   resides.
     * @param array $constructorArgs
     *   A list of arguments to pass to the constructor, may be an empty array.
     * @param bool $newInstance
     *   Whether to create a new instance (true) or reuse an already existing
     *   instance (false, default)
     *
     * @return object
     *
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     */
    public function getInstance($class, $subNamespace, array $constructorArgs = array(), $newInstance = false)
    {
        if (!isset($this->instances[$class]) || $newInstance) {
            // Try custom namespace.
            if (!empty($this->customNamespace)) {
                $fqClass = $this->tryNsInstance($class, $subNamespace, $this->customNamespace);
            }

            // Try the shop namespace and any parent namespaces.
            $namespaces = explode('\\', $this->shopNamespace);
            while (empty($fqClass) && !empty($namespaces)) {
                $namespace = implode('\\', $namespaces);
                $fqClass = $this->tryNsInstance($class, $subNamespace, $namespace);
                array_pop($namespaces);
            }

            if (empty($fqClass)) {
                throw new \InvalidArgumentException("Class $class not found in namespace $subNamespace");
            }

            // Create a new instance.
            // As PHP5.3 produces a fatal error when a class has no constructor
            // and newInstanceArgs() is called, we have to differentiate between
            // no arguments and arguments.
            if (empty($constructorArgs)) {
                $this->instances[$class] = new $fqClass();
            } else {
                $reflector = new ReflectionClass($fqClass);
                $this->instances[$class] = $reflector->newInstanceArgs($constructorArgs);
            }
        }
        return $this->instances[$class];
    }

    protected function tryNsInstance($class, $subNamespace, $namespace)
    {
        $fqClass = $namespace . '\\' . $subNamespace . '\\' . $class;
        $fileName = __DIR__ . DIRECTORY_SEPARATOR . '..' . str_replace('\\', DIRECTORY_SEPARATOR, substr($fqClass, strlen('/Siel/Acumulus'))) . '.php';
        // Checking if the file exists prevents warnings in Magento whose own
        // autoloader logs warnings when a class cannot be loaded.
        return is_readable($fileName) && class_exists($fqClass) ? $fqClass : '';
    }

    /**
     * Returns the namespace for the current cms.
     *
     * @return string
     *   The namespace for the current cms or the empty string if the current shop
     *   is not contained in a CMS namespace.
     */
    protected function getCmsNamespace()
    {
        // Get parent namespace of the shop namespace.
        $cmsNamespaceEnd = strrpos($this->shopNamespace, '\\');
        $cmsNamespace = substr($this->shopNamespace, 0, (int) $cmsNamespaceEnd);
        // But if that is Acumulus there's no CMS namespace.
        if (substr($cmsNamespace, -strlen('\\Acumulus')) === '\\Acumulus') {
            $cmsNamespace = '';
        }
        return $cmsNamespace;
    }
}
