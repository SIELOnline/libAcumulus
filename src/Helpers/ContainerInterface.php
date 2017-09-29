<?php
namespace Siel\Acumulus\Helpers;

/**
 * ContainerInterface defines an interface to retrieve:
 * - Instances of web shop specific overrides of the base classes and interfaces
 *   that are defined in the common package.
 * - Singleton instances from other namespaces.
 * - Instances that require some injection arguments in their constructor, that
 *   the implementing object can pass.
 */
interface ContainerInterface
{
    /**
     * Sets the language code.
     *
     * @param string $language
     *   A language or locale code, e.g. nl, nl-NL, or en-UK.
     *
     * @return $this
     */
    public function setLanguage($language);

    /**
     * Sets a custom namespace for customisations on top of the current shop.
     *
     * @param string $customNamespace
     */
    public function setCustomNamespace($customNamespace);

    /**
     * @return \Siel\Acumulus\Helpers\Log
     */
    public function getLog();

    /**
     * @return \Siel\Acumulus\Helpers\TranslatorInterface
     */
    public function getTranslator();

    /**
     * @return \Siel\Acumulus\Helpers\Requirements
     */
    public function getRequirements();

    /**
     * @return \Siel\Acumulus\Helpers\Mailer
     */
    public function getMailer();

    /**
     * @return \Siel\Acumulus\Helpers\Token
     */
    public function getToken();

    /**
     * @param string $type
     *   The type of form requested.
     *
     * @return \Siel\Acumulus\Helpers\Form
     */
    public function getForm($type);

    /**
     * @return \Siel\Acumulus\Helpers\FormRenderer
     */
    public function getFormRenderer();

    /**
     * @return \Siel\Acumulus\Helpers\FormMapper
     */
    public function getFormMapper();

    /**
     * @return \Siel\Acumulus\Web\Service
     */
    public function getService();

    /**
     * @return \Siel\Acumulus\Web\CommunicatorInterface
     */
    public function getCommunicator();

    /**
     * Creates a wrapper object for a source object identified by the given
     * parameters.
     *
     * @param string $invoiceSourceType
     *   The type of the invoice source to create.
     * @param string|object|array $invoiceSourceOrId
     *   The invoice source itself or its id to create a Source wrapper for.
     *
     * @return \Siel\Acumulus\Invoice\Source
     *   A wrapper object around a shop specific invoice source object.
     */
    public function getSource($invoiceSourceType, $invoiceSourceOrId);

    /**
     * Creates a wrapper object for an Acumulus invoice and its accompanying data.
     *
     * @return \Siel\Acumulus\Invoice\Invoice
     *   A wrapper object around an Acumulus invoice and its accompanying data.
     */
    public function getInvoice();

    /**
     * @return \Siel\Acumulus\Invoice\Completor
     */
    public function getCompletor();

    /**
     * @return \Siel\Acumulus\Invoice\CompletorInvoiceLines
     */
    public function getCompletorInvoiceLines();

    /**
     * @return \Siel\Acumulus\Invoice\FlattenerInvoiceLines
     */
    public function getFlattenerInvoiceLines();

    /**
     * @return \Siel\Acumulus\Invoice\CompletorStrategyLines
     */
    public function getCompletorStrategyLines();

    /**
     * @return \Siel\Acumulus\Invoice\Creator
     */
    public function getCreator();

    /**
     * @return \Siel\Acumulus\Config\ConfigInterface
     */
    public function getConfig();

    /**
     * @return \Siel\Acumulus\Config\ConfigStoreInterface
     */
    public function getConfigStore();

    /**
     * @return \Siel\Acumulus\Config\ShopCapabilitiesInterface
     */
    public function getShopCapabilities();

    /**
     * @return \Siel\Acumulus\Shop\InvoiceManager
     */
    public function getManager();

    /**
     * @return \Siel\Acumulus\Shop\AcumulusEntryModel
     */
    public function getAcumulusEntryModel();

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
     * @throws \ReflectionException
     */
    public function getInstance($class, $subNamespace, array $constructorArgs = array(), $newInstance = false);
}
