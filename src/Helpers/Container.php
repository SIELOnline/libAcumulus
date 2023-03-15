<?php
/**
 * @noinspection PhpIncompatibleReturnTypeInspection  The get/create...()
 *   methods are strong typed, but the internal getInstance() not, leading to
 *   this warning all over the place.
 */

declare(strict_types=1);

namespace Siel\Acumulus;

const Version = '7.6.6';

namespace Siel\Acumulus\Helpers;

use InvalidArgumentException;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Collectors\CollectorInterface;
use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ConfigStore;
use Siel\Acumulus\Config\ConfigUpgrade;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Data\AcumulusObject;
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

use function count;

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
 * library. That is, in 1 of the namespaces Collectors, Config, Helpers,
 * Invoice, or Shop. Note that there should be no need to override classes in
 * ApiClient or Data.
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
 * {@see setCustomNamespace()}. This will define an additional namespace to look
 * for before the above list as defined by the $shopNamespace argument is
 * traversed. Taking the above example, with 'MyShop\Custom' as custom
 * namespace, the container will first look for the class
 * \MyShop\Custom\Invoice\Creator, before looking for the above list of classes.
 *
 * By defining a custom namespace and placing your custom code in that
 * namespace, instead of changing the code in this library, it remains possible
 * to update this library to a newer version without loosing your
 * customisations. Note that, also in this case, you are responsible that this
 * class gets autoloaded.
 */
class Container
{
    private static Container $instance;

    /**
     * Returns the already created instance.
     *
     * Try not to use this: there should be only 1 instance of this class, but
     * that instance should be passed to the constructor, if a class needs
     * access. Current exception is the separate Acumulus Customise Invoice
     * module, that may not get the instance passed via a constructor.
     *
     * (PHP8: define return type as static)
     *
     * @return static
     *
     * @noinspection PhpUnused Should only be used in module own code, not in
     *   the library itself.
     */
    public static function getContainer(): Container
    {
        return static::$instance;
    }

    protected const baseNamespace = '\\Siel\\Acumulus';

    /**
     * The namespace for the current shop.
     */
    protected string $shopNamespace;
    /**
     * The namespace for customisations on top of the current shop.
     */
    protected string $customNamespace = '';
    /**
     * @var object[]
     *   Instances created that can be reused with subsequent get...() calls.
     */
    protected array $instances = [];
    protected bool $baseTranslationsAdded = false;
    /**
     * The language to display texts in.
     */
    protected string $language;

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
        static::$instance = $this;
    }

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
     */
    public function setLanguage(string $language): self
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
     * @noinspection PhpUnused  If used, it will be in shop specific code, not
     *   in this library itself.
     */
    public function setCustomNamespace(string $customNamespace): void
    {
        $this->customNamespace = $customNamespace;
    }

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
    public function addTranslations(string $class, string $subNameSpace): void
    {
        /** @noinspection PhpParamsInspection */
        $this->getTranslator()->add($this->getInstance($class, $subNameSpace));
    }

    public function getLog(): Log
    {
        return $this->getInstance('Log', 'Helpers', [Version]);
    }

    public function getRequirements(): Requirements
    {
        return $this->getInstance('Requirements', 'Helpers');
    }

    public function getUtil(): Util
    {
        return $this->getInstance('Util', 'Helpers');
    }

    public function getCountries(): Countries
    {
        return $this->getInstance('Countries', 'Helpers');
    }

    public function getMailer(): Mailer
    {
        return $this->getInstance('Mailer', 'Helpers', [
            $this->getConfig(),
            $this->getEnvironment(),
            $this->getTranslator(),
            $this->getLog(),
        ]);
    }

    /**
     * @noinspection PhpUnused  Called from shop specific code .
     */
    public function getCrashReporter(): CrashReporter
    {
        return $this->getInstance('CrashReporter', 'Helpers', [
            $this->getMailer(),
            $this->getEnvironment(),
            $this->getTranslator(),
            $this->getLog(),
        ]);
    }

    public function getToken(): Token
    {
        return $this->getInstance('Token', 'Helpers', [$this->getLog()]);
    }

    public function getFieldExpander(): FieldExpander
    {
        return $this->getInstance('FieldExpander', 'Helpers', [$this->getLog()]);
    }

    public function getFormHelper(): FormHelper
    {
        return $this->getInstance('FormHelper', 'Helpers', [$this->getTranslator()]);
    }

    public function getFormRenderer(bool $newInstance = false): FormRenderer
    {
        return $this->getInstance('FormRenderer', 'Helpers', [], $newInstance);
    }

    /**
     * @noinspection PhpUnused  Called from shop specific code .
     */
    public function getFormMapper(): FormMapper
    {
        return $this->getInstance('FormMapper', 'Helpers', [$this->getLog()]);
    }

    public function getAcumulusApiClient(): Acumulus
    {
        return $this->getInstance('Acumulus', 'ApiClient', [$this, $this->getEnvironment(), $this->getLog()]);
    }

    public function createAcumulusRequest(): AcumulusRequest
    {
        return $this->getInstance(
            'AcumulusRequest',
            'ApiClient',
            [$this, $this->getConfig(), $this->getEnvironment(), $this->getUtil(), $this->getLanguage()],
            true
        );
    }

    public function createHttpRequest(array $options): HttpRequest
    {
        return $this->getInstance('HttpRequest', 'ApiClient', [$options], true);
    }

    /**
     * @throws \Siel\Acumulus\ApiClient\AcumulusResponseException
     *   If the $httpResponse cannot be properly parsed.
     */
    public function createAcumulusResult(AcumulusRequest $acumulusRequest, HttpResponse $httpResponse): AcumulusResult
    {
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
        return $this->getInstance(
            'InvoiceAddResult',
            'Invoice',
            [$trigger, $this->getTranslator(), $this->getLog()],
            true
        );
    }

    public function getCompletor(): Completor
    {
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
        return $this->getInstance(
            'CompletorInvoiceLines',
            'Invoice',
            [$this->getFlattenerInvoiceLines(), $this->getConfig()]
        );
    }

    public function getFlattenerInvoiceLines(): FlattenerInvoiceLines
    {
        return $this->getInstance('FlattenerInvoiceLines', 'Invoice', [$this->getConfig()]);
    }

    public function getCompletorStrategyLines(): CompletorStrategyLines
    {
        return $this->getInstance('CompletorStrategyLines', 'Invoice', [$this->getConfig(), $this->getTranslator()]);
    }

    public function getCreator(): Creator
    {
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
     * Returns a {@see \Siel\Acumulus\Collectors\Collector} instance of the
     * given type.
     *
     * @param string $type
     *   The child type of the {@see \Siel\Acumulus\Collectors\Collector}
     *   requested. The class name only, without namespace and without Collector
     *   at the end.
     */
    public function getCollectorManager(): CollectorManager
    {
        $arguments = [
            $this->getFieldExpander(),
            $this->getShopCapabilities(),
            $this->getMappings(),
            $this,
            $this->getLog(),
        ];
        return $this->getInstance('CollectorManager', 'Collectors', $arguments);
    }

    /**
     * Returns a {@see \Siel\Acumulus\Collectors\Collector} instance of the
     * given type.
     *
     * @param string $type
     *   The child type of the {@see \Siel\Acumulus\Collectors\Collector}
     *   requested. The class name only, without namespace and without Collector
     *   at the end.
     */
    public function getCollector(string $type): CollectorInterface
    {
        $arguments = [
            $this->getFieldExpander(),
            $this,
            $this->getLog(),
        ];
        return $this->getInstance("{$type}Collector", 'Collectors', $arguments);
    }

    /**
     * Returns a {@see \Siel\Acumulus\Data\AcumulusObject} instance of the
     * given type.
     *
     * @param string $type
     *   The child type of the {@see \Siel\Acumulus\Data\AcumulusObject}
     *   requested.  The class name only, without namespace.
     */
    public function createAcumulusObject(string $type): AcumulusObject
    {
        return $this->getInstance($type, 'Data', [], true);
    }

    public function getEnvironment(): Environment
    {
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
        return $this->getInstance('ConfigUpgrade', 'Config', [
            $this->getConfig(),
            $this->getConfigStore(),
            $this->getRequirements(),
            $this->getLog(),
        ]);
    }

    public function getConfigStore(): ConfigStore
    {
        return $this->getInstance('ConfigStore', 'Config');
    }

    public function getShopCapabilities(): ShopCapabilities
    {
        return $this->getInstance('ShopCapabilities', 'Config', [$this->shopNamespace, $this->getTranslator()]);
    }

    public function getMappings(): Mappings
    {
        return $this->getInstance('Mappings', 'Config', [$this->getConfig(), $this->getShopCapabilities()]);
    }

    public function getInvoiceManager(): InvoiceManager
    {
        return $this->getInstance('InvoiceManager', 'Shop', [$this]);
    }

    public function getAcumulusEntryManager(): AcumulusEntryManager
    {
        return $this->getInstance('AcumulusEntryManager', 'Shop', [$this, $this->getLog()]);
    }

    /**
     * Returns a new AcumulusEntry instance.
     *
     * @param array|object $record
     *   The Acumulus entry data to populate the object with.
     */
    public function createAcumulusEntry($record): AcumulusEntry
    {
        return $this->getInstance('AcumulusEntry', 'Shop', [$record], true);
    }

    public function getAboutBlockForm(): AboutForm
    {
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
     *   The type of form requested. Allowed values are: 'register', 'config',
     *   'advanced', activate', batch', 'invoice', 'rate', 'uninstall'.
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
            $fqClass = null;
            // Try custom namespace.
            if (!empty($this->customNamespace)) {
                $fqClass = $this->tryNsInstance($class, $subNamespace, $this->customNamespace);
            }

            // Try the namespace passed to the constructor and any parent
            // namespaces, but stop at Acumulus.
            $namespaces = explode('\\', $this->shopNamespace);
            while ($fqClass === null && count($namespaces) > 0) {
                if (end($namespaces) === 'Acumulus') {
                    // We arrived at the base level (\...\Acumulus),
                    // try the \Siel\Acumulus\ level and stop.
                    $namespace = static::baseNamespace;
                    $namespaces = [];
                } else {
                    $namespace = implode('\\', $namespaces);
                    array_pop($namespaces);
                }
                $fqClass = $this->tryNsInstance($class, $subNamespace, $namespace);
            }

            if ($fqClass === null) {
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
     *   The class to find, without namespace.
     * @param $subNamespace
     *   The sub namespace to add to the namespace.
     * @param $namespace
     *   The namespace to search in.
     *
     * @return string|null
     *   The full name of the class if it exists in the given namespace, or null
     *   if it does not exist.
     */
    protected function tryNsInstance($class, $subNamespace, $namespace): ?string
    {
        $fqClass = $this->getFqClass($class, $subNamespace, $namespace);
        // Checking if the file exists prevents warnings in Magento whose own
        // autoloader logs warnings when a class cannot be loaded.
        return class_exists($fqClass) ? $fqClass : null;
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
     *   THe "base" namespace where the class belongs to.
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
