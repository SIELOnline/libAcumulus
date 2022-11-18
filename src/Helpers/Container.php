<?php
namespace Siel\Acumulus;

const Version = '8.0.0-alpha1';

namespace Siel\Acumulus\Helpers;

use InvalidArgumentException;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Config\Config;

use Siel\Acumulus\Config\ConfigStore;
use Siel\Acumulus\Config\ConfigUpgrade;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Invoice\Collect;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\CompletorInvoiceLines;
use Siel\Acumulus\Invoice\CompletorStrategyLines;
use Siel\Acumulus\Invoice\Creator;
use Siel\Acumulus\Invoice\FlattenerInvoiceLines;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AboutForm;
use Siel\Acumulus\Shop\AcumulusEntry;

use Siel\Acumulus\Shop\AcumulusEntryManager;

use Siel\Acumulus\Shop\InvoiceManager;

use const Siel\Acumulus\Version;

/**
 * Container defines a dependency injector and factory pattern for this library.
 *
 * Principles
 * ----------
 * * This library is built with the idea to extract common code into base
 *   classes and have web shop specific classes extend those base classes with
 *   web shop specific overrides and implementations of abstract methods.
 * * Therefore, upon creating an instance, the most specialized class possible,
 *   will be instantiated and returned. See below how this is done.
 * * Container::getInstance() is the weakly typed instance getting method, but
 *   for almost all known classes in this library, a strongly typed getter is
 *   available as well. These getters also take care of getting the constructor
 *   arguments.
 * * By default only a single instance is created and this instance is returned
 *   on each subsequent request for an instance of that type.
 * * The strongly typed create... methods return a new instance on each call,
 *   turning this container also into a factory.
 *
 * Creating the container
 * ----------------------
 * Creating the container is normally done by code in the part adhering to your
 * web shop's architecture, e.g. a controller or model. That code must pass the
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
 * Suppose you are writing code for a web shop named <MyWebShop>: place your
 * classes in the namespace \Siel\Acumulus\<MyWebShop>.
 *
 * If you want to support multiple (major) versions of your webs hop, you can
 * add a "version level" to the namespace:
 * \Siel\Acumulus\<MyWebShop>\<MyWebShop><version> (note <MyWebShop> is repeated
 * as namespaces may not start with a digit). In this case you should place code
 * common for all versions in classes under \Siel\Acumulus\<MyWebShop>, but code
 * specific for a given version under
 * \Siel\Acumulus\<MyWebShop>\<MyWebShop><version>.
 *
 * The Magento and WooCommerce namespaces are examples of this.
 *
 * If your web shop is embedded in a CMS and there are multiple web shop
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
 * Note that there should be no need to override classes in ApiClient.
 *
 * If you do not want to use \Siel\Acumulus as starting part of your namespace,
 * you may replace Siel by your own vendor name and/or your department name, but
 * it has to be followed by \Acumulus\<...>. Note that if you do so, you are
 * responsible for ensuring that your classes are autoloaded.
 *
 * Whatever hierarchy you use, the container should be informed about it by
 * passing it as the 1st constructor argument. Example:
 * If 'MyVendorName\MyDepartmentName\Acumulus\MyCMS\MyWebShop\MyWebShop2' is
 * passed as 1st constructor argument to the Container and the container is
 * asked to return a {@see \Siel\Acumulus\Invoice\Creator}, it will look for
 * the following classes:
 * 1. \MyVendorName\MyDepartmentName\Acumulus\MyCMS\MyWebShop\MyWebShop2\Invoice\Creator
 * 2. \MyVendorName\MyDepartmentName\Acumulus\MyCMS\MyWebShop\Invoice\Creator
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
 * triggered by the Acumulus module. but if that turns out to be impossible, you
 * can define another level of namespace searching by calling
 * {@see Container::setCustomNamespace()}. This will define 1 additional
 * namespace to look for before the above list as defined by the $shopNamespace
 * argument is traversed. Taking the above example, with 'MyShop\Custom' as
 * custom namespace, the container will first look for the class
 * \MyShop\Custom\Invoice\Creator, before looking for the above list of classes.
 *
 * By defining a custom namespace and placing your custom code in that
 * namespace, instead of changing the code in this library, it remains possible
 * to update this library to a newer version without loosing your
 * customisations. Note that, also in this case, you are responsible that this
 * class gets autoloaded.
 *
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
class Container
{
    /** @const string */
    public const baseNamespace = '\\Siel\\Acumulus';

    /**
     * The namespace for the current shop.
     *
     * @var string
     */
    protected $shopNamespace;

    /**
     * @var string
     *   The namespace for customisations on top of the current shop.
     */
    protected $customNamespace = '';

    /** @var array */
    protected $instances = [];

    /** @var bool */
    protected $baseTranslationsAdded = false;

    /**
     * @var string
     *   The language to display texts in.
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
     *   A language or locale code, e.g. nl, nl-NL, or en-UK. Only the first 2
     *   characters will be used.
     */
    public function __construct(string $shopNamespace, string $language = 'nl')
    {
        $this->shopNamespace = '';
        if (strpos($shopNamespace, 'Acumulus') === false) {
            $this->shopNamespace = static::baseNamespace;
        }
        $this->shopNamespace .= '\\' . $shopNamespace;
        $this->setLanguage($language);
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Sets the language code.
     *
     * @param string $language
     *   A language or locale code, e.g. nl, nl-NL, or en-UK. Only the first 2
     *   characters will be used.
     *
     * @return $this
     */
    public function setLanguage(string $language): Container
    {
        $this->language = substr($language, 0, 2);
        return $this;
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
    public function setCustomNamespace(string $customNamespace)
    {
        $this->customNamespace = $customNamespace;
    }

    /**
     * @return \Siel\Acumulus\Helpers\Translator
     */
    public function getTranslator(): Translator
    {
        /** @var \Siel\Acumulus\Helpers\Translator $translator */
        $translator = $this->getInstance('Translator', 'Helpers', [$this->getLanguage()]);
        if (!$this->baseTranslationsAdded) {
            // Add some basic translations that are hard to add just-in-time.
            // @todo: add a hasTranslations interface to (largely) automate this on getInstance?
            $this->baseTranslationsAdded = true;
            $this->addTranslations('ModuleSpecificTranslations', 'Helpers');
            $this->addTranslations('ModuleTranslations', 'Shop');
            $this->addTranslations('SeverityTranslations', 'Helpers');
            $this->addTranslations('ResultTranslations', 'ApiClient');
            $this->addTranslations('ResultTranslations', 'Invoice');
        }
        return $translator;
    }

    /**
     * Adds a {@see TranslationCollection} to the {@see Translator}.
     *
     * @param string $class
     *   The name of the class to search. The class should extend
     *   {@see TranslationCollection}.
     * @param string $subNameSpace
     *   The namespace in which $class resides.
     *
     * @throws \InvalidArgumentException
     */
    public function addTranslations(string $class, string $subNameSpace)
    {
        /** @noinspection PhpParamsInspection */
        $this->getTranslator()->add($this->getInstance($class, $subNameSpace));
    }

    public function getLog(): Log
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Log', 'Helpers', [Version]);
    }

    public function getRequirements(): Requirements
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Requirements', 'Helpers');
    }

    public function getUtil(): Util
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Util', 'Helpers');
    }

    public function getCountries(): Countries
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Countries', 'Helpers');
    }

    public function getMailer(): Mailer
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Mailer', 'Helpers', [
            $this->getConfig(),
            $this->getEnvironment(),
            $this->getTranslator(),
            $this->getLog(),
        ]);
    }

    /** @noinspection PhpUnused  mostly called from shop specific code */
    public function getCrashReporter(): CrashReporter
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('CrashReporter', 'Helpers', [
            $this->getMailer(),
            $this->getEnvironment(),
            $this->getTranslator(),
            $this->getLog(),
        ]);
    }

    public function getToken(): Token
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Token', 'Helpers', [$this->getLog()]);
    }

    public function getFormHelper(): FormHelper
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FormHelper', 'Helpers', [$this->getTranslator()]);
    }

    public function getFormRenderer(bool $newInstance = false): FormRenderer
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FormRenderer', 'Helpers', [], $newInstance);
    }

    /**
     * @noinspection PhpUnused
     */
    public function getFormMapper(): FormMapper
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FormMapper', 'Helpers', [$this->getLog()]);
    }

    public function getAcumulusApiClient(): Acumulus
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Acumulus', 'ApiClient', [$this, $this->getEnvironment(), $this->getUtil(), $this->getLog()]);
    }

    public function createAcumulusRequest(): AcumulusRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance(
            'AcumulusRequest',
            'ApiClient',
            [$this, $this->getConfig(), $this->getEnvironment(), $this->getUtil(), $this->getLanguage()],
            true
        );
    }


    public function createHttpRequest(array $options): HttpRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('HttpRequest', 'ApiClient', [$options], true);
    }

    /**
     * @param \Siel\Acumulus\ApiClient\AcumulusRequest $acumulusRequest
     * @param \Siel\Acumulus\ApiClient\HttpResponse $httpResponse
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusResponseException
     *   If the $httpResponse cannot be properly parsed.
     */
    public function createAcumulusResult(AcumulusRequest $acumulusRequest, HttpResponse $httpResponse): AcumulusResult
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('AcumulusResult', 'ApiClient', [
            $acumulusRequest,
            $httpResponse,
            $this->getUtil(),
            $this->getTranslator(),
        ], true);
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
    public function createSource(string $invoiceSourceType, $invoiceSourceOrId): Source
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Source', 'Invoice', [$invoiceSourceType, $invoiceSourceOrId], true);
    }

    /**
     * Returns a new Acumulus invoice-add service result instance.
     *
     * @param string $trigger
     *   A string indicating the situation that triggered the need to get a new
     *   instance.
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   A wrapper object around an Acumulus invoice-add service result.
     */
    public function createInvoiceAddResult(string $trigger): InvoiceAddResult
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance(
            'InvoiceAddResult',
            'Invoice',
            [$trigger, $this->getTranslator(), $this->getLog()],
            true
        );
    }

    public function getCompletor(): Completor
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Completor', 'Invoice', [
            $this->getCompletorInvoiceLines(),
            $this->getCompletorStrategyLines(),
            $this->getCountries(),
            $this->getAcumulusApiClient(),
            $this->getConfig(),
            $this->getTranslator(),
            $this->getLog(),
        ], true);
    }

    public function getCompletorInvoiceLines(): CompletorInvoiceLines
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance(
            'CompletorInvoiceLines',
            'Invoice',
            [$this->getFlattenerInvoiceLines(), $this->getConfig()]
        );
    }

    public function getFlattenerInvoiceLines(): FlattenerInvoiceLines
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('FlattenerInvoiceLines', 'Invoice', [$this->getConfig()]);
    }

    public function getCompletorStrategyLines(): CompletorStrategyLines
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('CompletorStrategyLines', 'Invoice', [$this->getConfig(), $this->getTranslator()]);
    }

    public function getCreator(): Creator
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance(
            'Creator',
            'Invoice',
            [
                $this->getToken(),
                $this->getCountries(),
                $this->getShopCapabilities(),
                $this,
                $this->getConfig(),
                $this->getTranslator(),
                $this->getLog(),
            ]
        );
    }

    /**
     * Returns a collector instance of the given type.
     *
     * @param string $type
     *   The type of form requested.
     *
     * @return \Siel\Acumulus\Invoice\Collect
     */
    public function getCollect(string $type): Collect
    {
        $arguments = [
            $this->getToken(),
            $this->getConfig(),
        ];
        switch (strtolower($type)) {
            case 'customer':
            case 'invoice':
            case 'line':
            case 'emailAsPdf':
                $class = lcfirst($type);
                break;
            default;
                throw new InvalidArgumentException("Unknown collector type $type");
        }
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Collect' . $class, 'Invoice', $arguments);
    }

    public function getEnvironment(): Environment
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('Environment', 'Config', [$this->shopNamespace]);
    }

    public function getConfig(): Config
    {
        static $is1stTime = true;

        $log = $this->getLog();
        /** @var \Siel\Acumulus\Config\Config $config */
        $config = $this->getInstance('Config', 'Config', [
            $this->getConfigStore(),
            $this->getShopCapabilities(),
            [$this, 'getConfigUpgrade'],
            $this->getEnvironment(),
            $log,
        ]);
        if ($is1stTime) {
            $is1stTime = false;
            $pluginSettings = $config->getPluginSettings();
            $log->setLogLevel($pluginSettings['logLevel']);
        }
        return $config;
    }

    public function getConfigUpgrade(): ConfigUpgrade
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('ConfigUpgrade', 'Config', [
            $this->getConfig(),
            $this->getConfigStore(),
            $this->getRequirements(),
            $this->getLog(),
        ]);
    }

    public function getConfigStore(): ConfigStore
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('ConfigStore', 'Config');
    }

    public function getShopCapabilities(): ShopCapabilities
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('ShopCapabilities', 'Config', [$this->shopNamespace, $this->getTranslator()]);
    }

    public function getInvoiceManager(): InvoiceManager
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('InvoiceManager', 'Shop', [$this]);
    }

    public function getAcumulusEntryManager(): AcumulusEntryManager
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('AcumulusEntryManager', 'Shop', [$this, $this->getLog()]);
    }

    /**
     * Returns a new \Siel\Acumulus\Shop\AcumulusEntry instance.
     *
     * @param array|object $record
     *   The Acumulus entry data to populate the object with.
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntry
     */
    public function createAcumulusEntry($record): AcumulusEntry
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('AcumulusEntry', 'Shop', [$record], true);
    }

    public function getAboutBlockForm(): AboutForm
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance('AboutForm', 'Shop', [
            $this->getAcumulusApiClient(),
            $this->getShopCapabilities(),
            $this->getConfig(),
            $this->getEnvironment(),
            $this->getTranslator(),
        ]);
    }

    /**
     * Returns a form instance of the given type.
     *
     * @param string $type
     *   The type of form requested.
     *
     * @return \Siel\Acumulus\Helpers\Form
     */
    public function getForm(string $type): Form
    {
        $arguments = [];
        switch (strtolower($type)) {
            case 'register':
                $class = 'Register';
                $arguments[] = $this->getAboutBlockForm();
                $arguments[] = $this->getAcumulusApiClient();
                break;
            case 'config':
                $class = 'Config';
                $arguments[] = $this->getAboutBlockForm();
                $arguments[] = $this->getAcumulusApiClient();
                break;
            case 'advanced':
                $class = 'AdvancedConfig';
                $arguments[] = $this->getAboutBlockForm();
                $arguments[] = $this->getAcumulusApiClient();
                break;
            case 'activate':
                $class = 'ActivateSupport';
                $arguments[] = $this->getAboutBlockForm();
                $arguments[] = $this->getAcumulusApiClient();
                break;
            case 'batch':
                $class = 'Batch';
                $arguments[] = $this->getAboutBlockForm();
                $arguments[] = $this->getInvoiceManager();
                $arguments[] = $this->getAcumulusApiClient();
                break;
            case 'invoice':
                $class = 'InvoiceStatus';
                $arguments[] = $this->getInvoiceManager();
                $arguments[] = $this->getAcumulusEntryManager();
                $arguments[] = $this->getAcumulusApiClient();
                $arguments[] = $this;
                break;
            case 'rate':
                $class = 'RatePlugin';
                break;
            case 'uninstall':
                $class = 'ConfirmUninstall';
                $arguments[] = $this->getAcumulusApiClient();
                break;
            default;
                throw new InvalidArgumentException("Unknown form type $type");
        }
        $arguments = array_merge($arguments, [
            $this->getFormHelper(),
            $this->getShopCapabilities(),
            $this->getConfig(),
            $this->getEnvironment(),
            $this->getTranslator(),
            $this->getLog(),
        ]);
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getInstance($class . 'Form', 'Shop', $arguments);
    }

    /**
     * Returns an instance of the given class.
     *
     * This method should normally be avoided, use the get{Class}() methods as
     * they know (and hide) what arguments to inject into the constructor.
     *
     * The class is looked for in multiple namespaces, starting with the
     * $customNameSpace properties, continuing with the $shopNamespace property,
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
    public function getInstance(
        string $class,
        string $subNamespace,
        array $constructorArgs = [],
        bool $newInstance = false
    ): object {
        $instanceKey = "$subNamespace\\$class";
        if (!isset($this->instances[$instanceKey]) || $newInstance) {
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
                    $namespaces = [];
                } else {
                    $namespace = implode('\\', $namespaces);
                    array_pop($namespaces);
                }
                $fqClass = $this->tryNsInstance($class, $subNamespace, $namespace);
            }

            if (empty($fqClass)) {
                throw new InvalidArgumentException("Class $class not found in namespace $subNamespace");
            }

            // Create a new instance.
            $this->instances[$instanceKey] = new $fqClass(...$constructorArgs);
        }
        return $this->instances[$instanceKey];
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
    protected function tryNsInstance($class, $subNamespace, $namespace): string
    {
        $fqClass = $this->getFqClass($class, $subNamespace, $namespace);
        // Checking if the file exists prevents warnings in Magento whose own
        // autoloader logs warnings when a class cannot be loaded.
        return class_exists($fqClass) ? $fqClass : '';
    }

    /**
     * Returns the fully qualified class name.
     *
     * @param string $class
     *   The name of the class without any namespace part.
     * @param string $subNamespace
     *   The sub namespace where the class belongs to, e.g. helpers, invoice or
     *   shop.
     * @param string $namespace
     *   THe "base" namespace where the class belongs to
     *
     * @return string
     *   The fully qualified class name based on the base namespace, sub
     *   namespace and the class name.
     */
    protected function getFqClass(string $class, string $subNamespace, string $namespace): string
    {
        return $namespace . '\\' . $subNamespace . '\\' . $class;
    }
}
