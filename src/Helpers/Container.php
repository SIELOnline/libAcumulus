<?php
namespace Siel\Acumulus\Helpers;

use ReflectionClass;

/**
 * Container implements the ContainerInterface to allow other classes to
 * easily get the correct derived classes of the base classes.
 */
class Container implements ContainerInterface
{
    /**
     * The base directory where the Acumulus library is located.
     *
     * @var string
     */
    protected $baseDir;

    /** @const string */
    const baseNamespace = '\\Siel\\Acumulus\\';

    /**
     * The namespace for the current shop.
     *
     * @var string
     */
    protected $shopNamespace;

    /**
     * The namespace for customisations on top of the current shop.
     *
     * @var string
     */
    protected $customNamespace = '';

    /** @var array */
    protected $instances = array();

    /** @var bool */
    protected $moduleSpecificTranslationsAdded = false;

    /**
     * The language to display texts in.
     *
     * @var string
     */
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
        // Base directory of libAcumulus is parent directory of this file's
        // directory.
        $this->baseDir =  dirname(__DIR__);
        $this->shopNamespace = static::baseNamespace . $shopNamespace;
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
     *
     * Known usages: Magento1.
     * When Magento1 runs in compiled mode, the classes as are instantiated are
     * in the includes/src directory, in a flattened structure.  However, to
     * prevent errors or warnings, tryNsInstance will, before calling
     * class_exists(), first look for the existence of the class file in the
     * original directory structure. but that directory structure cannot be
     * derived by using __DIR__.
     */
    public function setBaseDir($baseDir)
    {
        $this->baseDir = $baseDir;
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
    public function getCountries()
    {
        return $this->getInstance('Countries', 'Helpers');
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
        return $this->getInstance('Creator', 'Invoice', array($this->getConfig(), $this->getToken(), $this->getCountries(), $this, $this->getTranslator(), $this->getLog()));
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
        $arguments = array(
            $this->getTranslator(),
            $this->getConfig(),
            $this->getShopCapabilities(),
        );
        switch (strtolower($type)) {
            case 'config':
                $class = 'Config';
                $arguments[] = $this->getService();
                break;
            case 'advanced':
                $class = 'AdvancedConfig';
                $arguments[] = $this->getService();
                break;
            case 'batch':
                $class = 'Batch';
                $arguments[] = $this->getManager();
                break;
            default;
                throw new \InvalidArgumentException("Unknown form type $type");
        }
        return $this->getInstance($class . 'Form', 'Shop', $arguments);
    }

    /**
     * {@inheritdoc}
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

    /**
     * Tries to find a class in the given namespace.
     *
     * @param $class
     *   The class to find.
     * @param $subNamespace
     *   The sub namespace to add to the namespace.
     * @param $namespace
     *   The namespace to search in.
     *
     * @return string
     *   The full name of the class or the empty string if it does not exist in
     *   the given namespace.
     */
    protected function tryNsInstance($class, $subNamespace, $namespace)
    {
        $fqClass = $this->getFqClass($class, $subNamespace, $namespace);
        $fileName = $this->getFileName($fqClass);
        // Checking if the file exists prevents warnings in Magento whose own
        // autoloader logs warnings when a class cannot be loaded.
        return is_readable($fileName) && class_exists($fqClass) ? $fqClass : '';
    }

    /**
     * Returns the fully qualified class name.
     *
     * @param string $class
     *   The name of the class without any namespace part.
     * @param string $subNamespace
     *   The sub namespace where the class belongs to, e.g helpers, invoice or
     *   shop.
     * @param string $namespace
     *   THe "base" namespace where the class belongs to
     *
     * @return string
     *   The fully qualified class name based on the base namespace, sub
     *   namespace and the class name.
     */
    protected function getFqClass($class, $subNamespace, $namespace)
    {
        return $namespace . '\\' . $subNamespace . '\\' . $class;
    }

    /**
     * Returns the file name (including path) where the given class resides.
     *
     * @param string $fqClass
     *   Fully qualified class name.
     *
     * @return string
     *   The file name (including path) where the given class resides.
     */
    protected function getFileName($fqClass)
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . str_replace('\\',DIRECTORY_SEPARATOR, substr($fqClass, strlen(static::baseNamespace))) . '.php';
    }
}
