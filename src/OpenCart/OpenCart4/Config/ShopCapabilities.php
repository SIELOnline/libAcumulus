<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Config;

use Siel\Acumulus\OpenCart\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * OC4 webshop specific capabilities.
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
            'address1' => '[payment_address_1|shipping_address_1]', // Order
            'address2' => '[payment_address_2|shipping_address_2]', // Order
            'postalCode' => '[payment_postcode|shipping_postcode]', // Order
            'city' => '[payment_city|shipping_city]', // Order
            'telephone' => '[telephone]', // Order
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
        /** @var \Opencart\Admin\Model\Setting\Extension $model */
        $model = $registry->getModel('setting/extension');
        $extensions = $model->getExtensionsByType('payment');
        foreach ($extensions as $extension) {
            $code = $extension['code'];
            if ($registry->config->get($prefix . $code . '_status')) {
                $enabled[] = $extension;
            }
        }
        return $this->paymentMethodToOptions($enabled);
    }

    /**
     * Turns the list into a translated list of select options.
     *
     * @param array[] $extensions
     *   A list with the enabled payment extensions. Each entry being a keyed
     *   array with keys: 'extension-id', 'extension', 'type' (= "payment"),
     *   'code'.
     *
     * @return array
     *   An array with the extensions as key and their translated name as value.
     */
    protected function paymentMethodToOptions(array $extensions): array
    {
        $results = [];
        $registry = $this->getRegistry();
        foreach ($extensions as $extension) {
            $directory = "extension/{$extension['extension']}/payment/";
            $registry->language->load($directory . $extension['code']);
            $results[$extension['code']] = $registry->language->get('heading_title');
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
                return $registry->getExtensionPageUrl('');
            case 'register':
            case 'activate':
            case 'advanced':
            case 'batch':
            case 'invoice':
                return $registry->getExtensionPageUrl($linkType);
            case 'logo':
                return $registry->getExtensionFileUrl('view/image/siel-logo.png');
            case 'pro-support-image':
                return $registry->getExtensionFileUrl('view/image/pro-support-opencart.png');
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=0nKmWpoNV0wtqeac43dqc5YUAcaHFJkldwy1alKD1G3EJHmC';
        }
        return parent::getLink($linkType);
    }
}
