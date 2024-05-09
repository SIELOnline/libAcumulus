<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

use RuntimeException;
use Siel\Acumulus\Api;

use function count;
use function strlen;

use const Siel\Acumulus\Version;

/**
 * Class Environment provides information about the environment, like shop name
 * and version, API uri, and versions of software/systems like PHP, DB, OS and
 * CMS.
 */
abstract class Environment
{
    public const Unknown = 'unknown';
    protected const QueryVariables = 'show variables where Variable_name in ("version", "version_comment")';

    protected array $data = [];
    protected string $shopNamespace;

    public function __construct(string $shopNamespace)
    {
        $this->shopNamespace = $shopNamespace;
    }

    /**
     * Sets the information about the environment.
     *
     * This method consists of 2 parts:
     * - Setting information that is shop independent
     * - Calling {@see setShopEnvironment()} for shop dependent information,
     *   and allowing shops to override the values set here.
     */
    protected function set(string $shopNamespace): void
    {
        $this->data['baseUri'] = Api::baseUri;
        $this->data['apiVersion'] = Api::apiVersion;
        $this->data['libraryVersion'] = Version;
        $this->data['hostName'] = $this->getHostName();
        $this->data['phpVersion'] = PHP_VERSION;
        $variables = $this->getDbVariables();
        $this->data['dbName'] = $variables['version_comment'] ?? '??MySQL??';
        $this->data['dbVersion'] = $variables['version'] ?? static::Unknown;
        $this->data['os'] = php_uname();
        /** @noinspection PhpComposerExtensionStubsInspection  false positive in projects with a higher level composer.json */
        $curlVersion = curl_version();
        /** @noinspection OffsetOperationsInspection */
        $this->data['curlVersion'] = "{$curlVersion['version']} (ssl: {$curlVersion['ssl_version']}; zlib: {$curlVersion['libz_version']})";
        $pos = strrpos($shopNamespace, '\\');
        $this->data['shopName'] = $pos !== false ? substr($shopNamespace, $pos + 1) : $shopNamespace;
        $this->data['shopVersion'] = static::Unknown;
        $this->data['moduleVersion'] = Version;
        $this->data['cmsName'] = '';
        $this->data['cmsVersion'] = '';
        $this->data['supportEmail'] = strtolower(rtrim($this->data['shopName'], '0123456789')) . '@acumulus.nl';
        $this->setShopEnvironment();
    }

    /**
     * Sets web shop specific environment values.
     *
     * Overriding methods should set:
     *  - shopName, but only if different from last part of shop namespace.
     *  - shopVersion.
     *  - moduleVersion, if different from this library's version.
     *  - cmsName, but only if the shop is an addon on top of a CMS.
     *  - cmsVersion, but only if the shop is an addon on top of a CMS.
     */
    abstract protected function setShopEnvironment(): void;

    /**
     * Returns the hostname of the current request.
     *
     * The hostname is returned without www. so it can be used as domain name
     * in constructing e-mail addresses.
     */
    protected function getHostName(): string
    {
        $hostName = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'example.com';
        if (empty($hostName)) {
            // In OC testing $_SERVER['HTTP_HOST'] is not null but equals false.
            $hostName = 'example.com';
        }
        if (($pos = strpos($hostName, 'www.')) !== false) {
            $hostName = substr($hostName, $pos + strlen('www.'));
        }
        return $hostName;
    }

    /**
     * Returns the values of the db variables 'version' and 'version_comment'.
     *
     * Only override if you cannot just override {@see executeQuery()} to return
     * an array with 2 associative arrays for the 2 variables to get.
     */
    protected function getDbVariables(): array
    {
        $queryResult = $this->executeQuery(static::QueryVariables);
        return array_combine(
            array_column($queryResult, 'Variable_name'),
            array_column($queryResult, 'Value')
        );
    }

    /**
     * Executes a query and returns the resulting rows.
     *
     * Override to use the shop specific database API.
     *
     * @return array[]
     */
    protected function executeQuery(string $query): array
    {
        throw new RuntimeException(__METHOD__ . ' not implemented');
    }

    /**
     * Returns information about the environment of this plugin.
     *
     * @return array
     *   A keyed array with information about the environment of this library:
     *   - 'baseUri'
     *   - 'apiVersion'
     *   - 'libraryVersion'
     *   - 'moduleVersion'
     *   - 'shopName'
     *   - 'shopVersion'
     *   - 'cmsName'
     *   - 'cmsVersion'
     *   - 'hostName'
     *   - 'phpVersion'
     *   - 'os'
     *   - 'curlVersion'
     *   - 'db'
     *   - 'dbVersion'
     *   - 'supportEmail'
     */
    public function get(): array
    {
        if (count($this->data) === 0) {
            $this->set($this->shopNamespace);
        }
        return $this->data;
    }

    /**
     * @return string[]
     *   A set of text lines that describe the environment. Can be used to
     *   display the environment as a bullet list. The lines are keyed by what
     *   can be seen as a 'header' for that line. The keys 'shop' and 'module'
     *   can be translated (using shop specific terminology).
     */
    public function getAsLines(): array
    {
        $environment = $this->get();
        return [
            'shop' => "{$environment['shopName']} {$environment['shopVersion']}"
                . (!empty($environment['cmsName']) ? " on {$environment['cmsName']} {$environment['cmsVersion']}" : ''),
            'module' => "Acumulus {$environment['moduleVersion']}; Library: {$environment['libraryVersion']}",
            'PHP' => "{$environment['phpVersion']};" . " (Curl: {$environment['curlVersion']})",
            'Database' => "{$environment['dbName']} {$environment['dbVersion']}",
            'Server' => $environment['hostName'],
            'OS' => $environment['os'],
        ];
    }
}
