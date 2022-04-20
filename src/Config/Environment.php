<?php
namespace Siel\Acumulus\Config;

use Siel\Acumulus\Api;

use const Siel\Acumulus\Version;

/**
 * Class Environment defines environment constants, like shop name and version,
 * API uri, and versions of software/systems like PHP, DB, OS and CMS.
 */
class Environment
{
    protected const Unknown = 'unknown';

    protected $data = [];

    public function __construct(string $shopNamespace)
    {
        $this->data['baseUri'] = Api::baseUri;
        $this->data['apiVersion'] = Api::apiVersion;
        $this->data['libraryVersion'] = Version;
        $this->data['hostName'] = $this->getHostName();
        $this->data['phpVersion'] = phpversion();
        $this->data['db'] = '';
        $this->data['dbVersion'] = '';
        $this->data['os'] = php_uname();
        $curlVersion = curl_version();
        $this->data['curlVersion'] = "{$curlVersion['version']} (ssl: {$curlVersion['ssl_version']}; zlib: {$curlVersion['libz_version']})";
        $this->setShopEnvironment($shopNamespace);
        if (empty($this->data['shopName'])) {
            $pos = strrpos($shopNamespace, '\\');
            $this->data['shopName'] = $pos !== false ? substr($shopNamespace, $pos + 1) : $shopNamespace;
        }
        if (empty($this->data['shopVersion'])) {
            $this->data['shopVersion'] = static::Unknown;
        }
        if (empty($this->data['moduleVersion'])) {
            $this->data['moduleVersion'] = Version;
        }
        if (empty($this->data['cmsName'])) {
            $this->data['cmsName'] = '';
            $this->data['cmsVersion'] = '';
        }
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
    protected function setShopEnvironment(string $shopNamespace): void {}

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
     *   - 'jsonVersion'
     */
    public function get(): array
    {
        return $this->data;
    }
}
