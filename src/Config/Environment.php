<?php
namespace Siel\Acumulus\Config;

use Siel\Acumulus\Api;

use const Siel\Acumulus\Version;

/**
 * Class Environment defines environment constants, like shop name and version,
 * API uri, and versions of software/systems like PHP, DB, OS and CMS.
 */
abstract class Environment
{
    protected const Unknown = 'unknown';
    protected const QueryVariables = 'show variables where Variable_name in ("version", "version_comment")';

    protected /*array*/ $data = [];
    protected /*string*/ $shopNamespace;

    public function __construct(string $shopNamespace)
    {
        $this->shopNamespace = $shopNamespace;
    }

    protected function set(string $shopNamespace)
    {
        $this->data['baseUri'] = Api::baseUri;
        $this->data['apiVersion'] = Api::apiVersion;
        $this->data['libraryVersion'] = Version;
        $this->data['hostName'] = $this->getHostName();
        $this->data['phpVersion'] = phpversion();
        $variables = $this->getDbVariables();
        $this->data['dbName'] = $variables['version_comment'] ?? '??MySQL??';
        $this->data['dbVersion'] = $variables['version'] ?? static::Unknown;
        $this->data['os'] = php_uname();
        $curlVersion = curl_version();
        $this->data['curlVersion'] = "{$curlVersion['version']} (ssl: {$curlVersion['ssl_version']}; zlib: {$curlVersion['libz_version']})";
        $pos = strrpos($shopNamespace, '\\');
        $this->data['shopName'] = $pos !== false ? substr($shopNamespace, $pos + 1) : $shopNamespace;
        $this->data['shopVersion'] = static::Unknown;
        $this->data['moduleVersion'] = Version;
        $this->data['cmsName'] = '';
        $this->data['cmsVersion'] = '';
        $this->setShopEnvironment();
    }

    /**
     * Returns the hostname of the current request.
     *
     * The hostname is returned without www. so it can be used as domain name
     * in constructing e-mail addresses.
     *
     * @return string
     *   The hostname of the current request.
     */
    protected function getHostName(): string
    {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $hostName = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);
        }
        if (!empty($hostName)) {
            if (($pos = strpos($hostName, 'www.')) !== false) {
                $hostName = substr($hostName, $pos + strlen('www.'));
            }
        } else {
            $hostName = 'example.com';
        }
        return $hostName;
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
    protected function setShopEnvironment(): void {}

    /**
     * Returns the values of the db variables 'version' and 'version_comment'.
     *
     * Only override if you cannot just override {@see executeQuery} to return
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
    abstract protected function executeQuery(string $query): array;

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
     */
    public function get(): array
    {
        if (count($this->data) === 0) {
            $this->set($this->shopNamespace);
        }
        return $this->data;
    }
}
