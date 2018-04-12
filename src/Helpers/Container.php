<?php
namespace Siel\Acumulus\Helpers;

use ReflectionClass;

/**
 * Container defines an interface to retrieve:
 * - Instances of web shop specific overrides of the base classes and interfaces
 *   that are defined in the common package.
 * - Singleton instances from other namespaces.
 * - Instances that require some injection arguments in their constructor, that
 *   the calling object can not pass.
 */
class Container
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
     * Sets the base directory of the Acumulus library.
     *
     * Known usages: Magento1.
     * When Magento1 runs in compiled mode, the classes as are instantiated are
     * in the includes/src directory, in a flattened structure. However, to
     * prevent errors or warnings, tryNsInstance will, before calling
     * class_exists(), first look for the existence of the class file in the
     * original directory structure. but that directory structure cannot be
     * derived by using __DIR__.
     *
     * @param string $baseDir
     */
    public function setBaseDir($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * Sets a custom namespace for customisations on top of the current shop.
     *
     * @param string $customNamespace
     */
    public function setCustomNamespace($customNamespace)
    {
        $this->customNamespace = $customNamespace;
    }

    /**
     * @return \Siel\Acumulus\Helpers\Translator
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
     * @return \Siel\Acumulus\Helpers\Log
     */
    public function getLog()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Log', 'Helpers');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Requirements
     */
    public function getRequirements()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Requirements', 'Helpers');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Countries
     */
    public function getCountries()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Countries', 'Helpers');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Mailer
     */
    public function getMailer()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Mailer', 'Helpers', array($this->getConfig(), $this->getTranslator(), $this->getLog()));
    }

    /**
     * @return \Siel\Acumulus\Helpers\Token
     */
    public function getToken()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Token', 'Helpers', array($this->getLog()));
    }

    /**
     * @return \Siel\Acumulus\Helpers\FormRenderer
     */
    public function getFormRenderer()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FormRenderer', 'Helpers');
    }

    /**
     * @return \Siel\Acumulus\Helpers\FormMapper
     */
    public function getFormMapper()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FormMapper', 'Helpers', array($this->getLog()));
    }

    /**
     * @return \Siel\Acumulus\Web\Service
     */
    public function getService()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Service', 'Web', array($this->getCommunicator(), $this->getConfig(), $this->getTranslator()));
    }

    /**
     * Creates and returns a new \Siel\Acumulus\Web\Result instance.
     *
     * @return \Siel\Acumulus\Web\Result
     */
    public function getResult()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Result', 'Web', array($this->getTranslator()), true);
    }

    /**
     * @return \Siel\Acumulus\Web\Communicator
     */
    public function getCommunicator()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Communicator', 'Web', array($this->getConfig(), $this->getLog(), $this, $this->getTranslator()));
    }

    /**
     * Creates a new wrapper object for the given invoice source.
     *
     * @param string $invoiceSourceType
     *   The type of the invoice source to create.
     * @param int|object|array $invoiceSourceOrId
     *   The invoice source itself or its id to create a
     *   \Siel\Acumulus\Invoice\Source instance for.
     *
     * @return \Siel\Acumulus\Invoice\Source
     *   A wrapper object around a shop specific invoice source object.
     */
    public function getSource($invoiceSourceType, $invoiceSourceOrId)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Source', 'Invoice', array($invoiceSourceType, $invoiceSourceOrId), true);
    }

    /**
     * Returns a new Acumulus invoice-add service result instance.
     *
     * @param string $trigger
     *   A string indicating the situation that triggered the need to get a new
     *   instance.
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   A wrapper object around an Acumulus invoice-add service result.
     */
    public function getInvoiceResult($trigger)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Result', 'Invoice', array($this->getTranslator(), $trigger), true);
    }

    /**
     * @return \Siel\Acumulus\Invoice\Completor
     */
    public function getCompletor()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Completor', 'Invoice', array($this->getConfig(), $this->getCompletorInvoiceLines(), $this->getCompletorStrategyLines(), $this->getCountries(), $this->getTranslator(), $this->getService()));
    }

    /**
     * @return \Siel\Acumulus\Invoice\CompletorInvoiceLines
     */
    public function getCompletorInvoiceLines()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('CompletorInvoiceLines', 'Invoice', array($this->getConfig(), $this->getFlattenerInvoiceLines()));
    }

    /**
     * @return \Siel\Acumulus\Invoice\FlattenerInvoiceLines
     */
    public function getFlattenerInvoiceLines()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FlattenerInvoiceLines', 'Invoice', array($this->getConfig()));
    }

    /**
     * @return \Siel\Acumulus\Invoice\CompletorStrategyLines
     */
    public function getCompletorStrategyLines()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('CompletorStrategyLines', 'Invoice', array($this->getConfig(), $this->getTranslator()));
    }

    /**
     * @return \Siel\Acumulus\Invoice\Creator
     */
    public function getCreator()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Creator', 'Invoice', array($this->getConfig(), $this->getToken(), $this->getCountries(), $this, $this->getTranslator(), $this->getLog()));
    }

    /**
     * @return \Siel\Acumulus\Config\Config
     */
    public function getConfig()
    {
        static $is1stTime = true;

        $log = $this->getLog();
        $configStore = $this->getConfigStore();

        /** @var \Siel\Acumulus\Config\Config $config */
        $config = $this->getInstance('Config', 'Config', array($configStore, $this->getShopCapabilities(), $this->getTranslator(), $log));
        if ($is1stTime) {
            $configStore->setConfig($config);
            $pluginSettings = $config->getPluginSettings();
            $environment = $config->getEnvironment();
            $log->setLogLevel($pluginSettings['logLevel']);
            $log->setLibraryVersion($environment['libraryVersion']);
            $configStore->setConfig($config);
            $is1stTime = false;
        }
        return $config;
    }

    /**
     * @return \Siel\Acumulus\Config\ConfigStore
     */
    public function getConfigStore()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('ConfigStore', 'Config');
    }

    /**
     * @return \Siel\Acumulus\Config\ShopCapabilities
     */
    public function getShopCapabilities()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('ShopCapabilities', 'Config', array($this->getTranslator(), $this->shopNamespace, $this->getLog()));
    }

    /**
     * @return \Siel\Acumulus\Shop\InvoiceManager
     */
    public function getManager()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('InvoiceManager', 'Shop', array($this));
    }

    /**
     * @return \Siel\Acumulus\Shop\AcumulusEntryManager
     */
    public function getAcumulusEntryManager()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('AcumulusEntryManager', 'Shop', array($this, $this->getLog()));
    }

    /**
     * Returns a new \Siel\Acumulus\Shop\AcumulusEntry instance.
     *
     * @param array|object $record
     *   The Acumulus entry data to populate the object with.
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntry
     */
    public function getAcumulusEntry($record)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('AcumulusEntry', 'Shop', array($record), true);
    }

    /**
     * Returns a form instance of the given type.
     *
     * @param string $type
     *   The type of form requested.
     *
     * @return \Siel\Acumulus\Helpers\Form
     */
    public function getForm($type)
    {
        $arguments = array(
            $this->getTranslator(),
            $this->getConfig(),
        );
        switch (strtolower($type)) {
            case 'config':
                $class = 'Config';
                $arguments[] = $this->getShopCapabilities();
                $arguments[] = $this->getService();
                break;
            case 'advanced':
                $class = 'AdvancedConfig';
                $arguments[] = $this->getShopCapabilities();
                $arguments[] = $this->getService();
                break;
            case 'batch':
                $class = 'Batch';
                $arguments[] = $this->getShopCapabilities();
                $arguments[] = $this->getManager();
                break;
            case 'shop_order':
                $class = 'ShopOrderOverview';
                $arguments[] = $this->getService();
                $arguments[] = $this->getAcumulusEntryManager();
                break;
            default;
                throw new \InvalidArgumentException("Unknown form type $type");
        }
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance($class . 'Form', 'Shop', $arguments);
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * Returns an instance of the given class.
     *
     * This method should normally be avoided, use the get{Class}() methods as
     * they know (and hide) what arguments to inject into the constructor.
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
                /** @noinspection PhpUnhandledExceptionInspection */
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
