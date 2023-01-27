<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Config;

use Siel\Acumulus\OpenCart\Config\ShopCapabilities as ShopCapabilitiesBase;

use function defined;

/**
 * OC3 webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopDefaults(): array
    {
        return [
            'contactYourId' => '[customer_id]', // Order
            'companyName1' => '[payment_company]', // Order
            'fullName' => '[firstname+lastname]', // Order
            'address1' => '[payment_address_1]', // Order
            'address2' => '[payment_address_2]', // Order
            'postalCode' => '[payment_postcode]', // Order
            'city' => '[payment_city]', // Order
            'vatNumber' => '[payment_tax_id]', // Order
            'telephone' => '[telephone]', // Order
            'fax' => '[fax]', // Order
            'email' => '[email]', // Order

            // Invoice lines defaults.
            'itemNumber' => '[sku|upc|ean|jan|isbn|mpn]',
            'productName' => '[name+"("&model&")"]',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods(): array
    {
        $registry = $this->getRegistry();
        $prefix = 'payment_';
        $enabled = [];
        /** @var \ModelSettingExtension $model */
        $model = $registry->getModel('setting/extension');
        $extensions = $model->getInstalled('payment');
        foreach ($extensions as $extension) {
            if ($registry->config->get($prefix . $extension . '_status')) {
                $enabled[] = $extension;
            }
        }
        return $this->paymentMethodToOptions($enabled);
    }

    /**
     * Turns the list into a translated list of select options.
     *
     * @param array $extensions
     *
     * @return array
     *   An array with the extensions as key and their translated name as value.
     */
    protected function paymentMethodToOptions(array $extensions): array
    {
        $results = [];
        $registry = $this->getRegistry();
        $directory = 'extension/payment/';
        foreach ($extensions as $extension) {
            $registry->language->load($directory . $extension);
            $results[$extension] = $registry->language->get('heading_title');
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink(string $linkType): string
    {
        $registry = $this->getRegistry();
        switch ($linkType) {
            case 'config':
                return $registry->getLink($registry->getLocation());
            case 'register':
            case 'activate':
            case 'advanced':
            case 'batch':
            case 'invoice':
                return $registry->getLink($registry->getLocation() . '/' . $linkType);
            case 'logo':
                return (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . 'view/image/acumulus/siel-logo.png';
            case 'pro-support-image':
                return (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . 'view/image/acumulus/pro-support-opencart.png';
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=0nKmWpoNV0wtqeac43dqc5YUAcaHFJkldwy1alKD1G3EJHmC';
        }
        return parent::getLink($linkType);
    }
}
