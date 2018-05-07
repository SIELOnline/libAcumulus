<?php
namespace Siel\Acumulus\Helpers;

use ReflectionClass;
use Siel\Acumulus\PluginConfig;

/**
 * Container defines a dependency injector pattern for this library.
 *
 * Principles
 * ----------
 * * This library is built with the idea to extract common code into base
 *   classes and have webshop specific classes extend those base classes with
 *   webshop specific overrides and implementations of abstract methods.
 * * Therefore, upon creating an instance, the most specialized class possible,
 *   will be instantiated and returned. See below how this is done.
 * * Container::getInstance() is the weakly typed instance getting method, but
 *   for almost all known classes in this library, a strongly typed getter is
 *   available as well. This getter also takes care of getting the constructor
 *   arguments.
 * * By default only a single instance is created and this instance is returned
 *   on each subsequent request for an instance of that type. The strongly typed
 *   getters do know when this behaviour is not wanted (mostly when specific
 *   arguments have to be passed) and will create fresh instances in those
 *   cases.
 *
 * Creating the container
 * ----------------------
 * Creating the container is normally done by code in the part adhering to your
 * webshop's architecture, e.g. a controller or model. That code must pass the
 * following arguments:
 * * $shopNamespace: defines the namespace hierarchy where to look for
 *   specialized classes. This is further explained below.
 * * $language: the language to use with translating. As the container is able
 *   to pass constructor arguments all by itself, it must know the current
 *   language, as the {@see Translator} is often used as constructor argument
 *   for other objects.
 *
 * How the container finds the class to instantiate
 * ------------------------------------------------
 * Finding the most specialized class is not done via configuration, as is
 * normally done in container implementations, but via namespace hierarchy.
 *
 * Suppose you are writing code for a webshop named <MyWebShop>: place your
 * classes in the namespace \Siel\Acumulus\<MyWebShop>.
 *
 * If you want to support multiple (major) versions of your webshop, you can add
 * a "version level" to the namespace:
 * \Siel\Acumulus\<MyWebShop>\<MyWebShop><version> (note <MyWebShop> is repeated
 * as namespaces may not start with a digit). In this case you should place code
 * common for all versions in classes under \Siel\Acumulus\<MyWebShop>, but code
 * specific for a given version under
 * \Siel\Acumulus\<MyWebShop>\<MyWebShop><version>.
 *
 * The Magento and WooCommerce namespaces are examples of this.
 *
 * If your webshop is embedded in a CMS and there are multiple webshop
 * extensions for that CMS, you can add a "CMS level" to the namespace:
 * \Siel\Acumulus\<MyCMS>\<MyWebShop>[\<MyWebShop><version>]. Classes at the CMS
 * level should contain code common for the CMS, think of configuration storage,
 * logging, mailing and database access.
 *
 * The Joomla namespace is an example of this. The WooCommerce namespace could
 * be an example of this, but as currently no support for other WordPress shop
 * extensions is foreseen, the WordPress namespace was not added to the
 * hierarchy.
 *
 * At whatever level you are overriding classes from this library, you always
 * have to place them in the same sub namespace as where they are placed in this
 * library. That is, in 1 of the namespaces Config, Helpers, Invoice, or Shop.
 * Note that there should be no need to override classes in Web.
 *
 * If you do no want to use \Siel\Acumulus as starting part of your namespace,
 * you may replace Siel by your own vendor name and/or your department name, but
 * it has to be followed by \Acumulus\<...>. Note that if you do so, you are
 * responsible for ensuring that your classes are autoloaded.
 *
 * Whatever hierarchy you use, the container should be informed about it by
 * passing it as the 1st constructor argument. Example:
 * If 'MyVendorName\MyDepartmentName\Acumulus\MyCMS\MyWebshop\MyWebShop2' is
 * passed as 1st constructor argument to the Container and the container is
 * asked to return a {@see \Siel\Acumulus\Invoice\Creator}, it will look for
 * the following classes:
 * 1. \MyVendorName\MyDepartmentName\Acumulus\MyCMS\MyWebshop\MyWebShop2\Invoice\Creator
 * 2. \MyVendorName\MyDepartmentName\Acumulus\MyCMS\MyWebshop\Invoice\Creator
 * 3. \MyVendorName\MyDepartmentName\Acumulus\MyCMS\Invoice\Creator
 * 4. \Siel\Acumulus\Invoice\Creator
 *
 * Customising the library
 * -----------------------
 * There might be cases where you are not implementing a new extension but are
 * using an existing extension and just want to adapt some behaviour of this
 * library to your specific situation.
 *
 * Most of these problems can be solved by reacting to one of the events
 * triggered by the Acumulus module. but f that turns out to be impossible, you
 * can define another level of namespace searching by calling
 * {@see Container::setCustomNamespace()}. This will define 1 additional
 * namespace to look for before the above list as defined by the $shopNamespace
 * argument is traversed. Taking the above example, and if you would have set
 * 'MyShop\Custom' as custom namespace, the container
 * will first look for the class \MyShop\Custom\Invoice\Creator, before
 * looking for the above list of classes.
 *
 * By defining a custom namespace and placing your custom code in that
 * namespace, instead of changing the code in this library, it remains possible
 * to update this library to a newer version without loosing your
 * customisations. Note that, also in this case, you are responsible that this
 * class gets autoloaded.
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
    const baseNamespace = '\\Siel\\Acumulus';

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
     *   The most specialized namespace to start searching for extending
     *   classes. This does not have to start with Siel\Acumulus and must not
     *   start or end with a \.
     * @param string $language
     *   A language or locale code, e.g. nl, nl-NL, or en-UK.
     */
    public function __construct($shopNamespace, $language = 'nl')
    {
        // Base directory of libAcumulus is parent directory of this file's
        // directory.
        $this->baseDir =  dirname(__DIR__);
        $this->shopNamespace = '';
        if (strpos($shopNamespace, 'Acumulus') === false) {
            $this->shopNamespace = static::baseNamespace;
        }
        $this->shopNamespace .= '\\' . $shopNamespace;
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
     *   A custom namespace that will be searched for first, before traversing
     *   the shopNamespace hierarchy in search for a requested class.
     *   It should start with a \, but not end with it.
     *
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
        return $this->getInstance('Log', 'Helpers', array(PluginConfig::Version));
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
     * @return \Siel\Acumulus\Helpers\FormHelper
     */
    public function getFormHelper()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FormHelper', 'Helpers');
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
        return $this->getInstance('Communicator', 'Web', array($this, $this->getConfig(), $this->getTranslator(), $this->getLog()));
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
        return $this->getInstance('Result', 'Invoice', array($trigger, $this->getTranslator()), true);
    }

    /**
     * @return \Siel\Acumulus\Invoice\Completor
     */
    public function getCompletor()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Completor', 'Invoice', array($this->getCompletorInvoiceLines(), $this->getCompletorStrategyLines(), $this->getCountries(), $this->getService(), $this->getConfig(), $this->getTranslator()));
    }

    /**
     * @return \Siel\Acumulus\Invoice\CompletorInvoiceLines
     */
    public function getCompletorInvoiceLines()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('CompletorInvoiceLines', 'Invoice', array($this->getFlattenerInvoiceLines(), $this->getConfig()));
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
        return $this->getInstance('Creator', 'Invoice', array($this->getToken(), $this->getCountries(), $this->getShopCapabilities(), $this, $this->getConfig(), $this->getTranslator(), $this->getLog()));
    }

    /**
     * @return \Siel\Acumulus\Config\Config
     */
    public function getConfig()
    {
        static $is1stTime = true;

        $log = $this->getLog();
        /** @var \Siel\Acumulus\Config\Config $config */
        $config = $this->getInstance('Config', 'Config', array($this->getConfigStore(), $this->getShopCapabilities(), $this->getTranslator(), $log));
        if ($is1stTime) {
            $pluginSettings = $config->getPluginSettings();
            $log->setLogLevel($pluginSettings['logLevel']);
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
        return $this->getInstance('ShopCapabilities', 'Config', array($this->shopNamespace, $this->getTranslator(), $this->getLog()));
    }

    /**
     * @return \Siel\Acumulus\Shop\InvoiceManager
     */
    public function getInvoiceManager()
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
        $arguments = array();
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
                $arguments[] = $this->getInvoiceManager();
                break;
            case 'shop_order':
                $class = 'ShopOrderOverview';
                $arguments[] = $this->getAcumulusEntryManager();
                $arguments[] = $this->getService();
                break;
            default;
                throw new \InvalidArgumentException("Unknown form type $type");
        }
        $arguments = array_merge($arguments, array(
            $this->getFormHelper(),
            $this->getShopCapabilities(),
            $this->getConfig(),
            $this->getTranslator(),
        ));
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

            // Try the namespace passed to the constructor and any parent
            // namespaces, but stop at Acumulus.
            $namespaces = explode('\\', $this->shopNamespace);
            while (empty($fqClass) && !empty($namespaces)) {
                if (end($namespaces) === 'Acumulus') {
                    // Base level is always \Siel\Acumulus, even if
                    // \MyVendorName\Acumulus\MyWebShop was set as shopNamespace.
                    $namespace = static::baseNamespace;
                    $namespaces = array();
                } else {
                    $namespace = implode('\\', $namespaces);
                    array_pop($namespaces);
                }
                $fqClass = $this->tryNsInstance($class, $subNamespace, $namespace);
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
        return $this->baseDir . str_replace('\\',DIRECTORY_SEPARATOR, substr($fqClass, strlen(static::baseNamespace))) . '.php';
    }
}
