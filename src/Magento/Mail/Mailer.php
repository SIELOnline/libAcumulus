<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Mail;

use Exception;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Mail\MimeInterface;
use Magento\Framework\Mail\MimeMessage;
use Magento\Framework\Mail\MimePart;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Backend\App\ConfigInterface as MagentoAppConfigInterface;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Mail\Mailer as BaseMailer;
use Throwable;

use function func_get_args;

/**
 * Extends the base mailer class to send mail using the Magento 2 mail features.
 */
class Mailer extends BaseMailer
{
    /**
     * Sends an email.
     *
     * @return \Throwable|bool
     *   Success (true) or a {@see \Throwable}.
     *
     * @todo: PHP 8.2: true instead of bool.
     */
    protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): Throwable|bool
    {
        return version_compare($this->getMagentoVersion(), '2.4.8', '>=')
            ? $this->send248(...func_get_args())
            : $this->send247(...func_get_args());
    }

    protected function getMagentoVersion(): string
    {
        /** @var \Magento\Framework\App\ProductMetadataInterface $productMetadata */
        $productMetadata = Registry::getInstance()->get(ProductMetadataInterface::class);
        try {
            $version = $productMetadata->getVersion();
        } catch (Exception) {
            // In CLI mode (php bin/magento ...) getVersion() throws an exception.
            $version = 'UNKNOWN';
        }
        return $version;
    }

    /**
     * Creates and sends an email in Magento 2.4.8.
     *
     * In Magento 2.4.8 the ower level mail creation and sending library has been changed.
     * As we do not use the higher level template based mail component, we had to rewrite
     * our mail handler.
     *
     * We used {@see \Magento\Framework\Mail\Template\TransportBuilder::prepareMessage()}
     * of how to create and send mail. This method is template based but in the end
     * collects the message fields in an array that is passed further down.
     */
    protected function send248(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ): Throwable|bool {
        try {
            /**
             * See {@see \Magento\Framework\Mail\Template\TransportBuilder::prepareMessage()}
             * and {@see \Magento\Framework\Mail\Template\TransportBuilder::addAddressByType()}
             * for how that template based Transport builder class puts the mail contents
             * and address fields in an array, that will be passed further down.
             *
             * We do not use a template so we replace that part by just creating the mime
             * parts ourselves and adding the to the array.
             */
            $message = new AcumulusMagentoEmailMessage(
                new MimeMessage([new MimePart($bodyHtml, MimeInterface::TYPE_HTML), new MimePart($bodyText, MimeInterface::TYPE_TEXT),]),
                [$to],
                [$from, $fromName],
                null,
                null,
                null,
                null,
                $subject
            );
            $mailTransport = $this->getTransportFactory()->create(['message' => $message]);
            $mailTransport->sendMessage();
            return true;
        } catch (Throwable $e) {
            return $e;
        }
    }

    protected function getConfig(): MagentoAppConfigInterface
    {
        return Registry::getInstance()->get(MagentoAppConfigInterface::class);
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection The default implementation is not needed.
     */
    public function getFrom(): string
    {
        $result = $this->getConfig()->getValue('trans_email/ident_general/email');
        return !empty($result) && !str_contains($result, 'example.com') ? $result : $this->getTo();
    }

    public function getFromName(): string
    {
        $result = $this->getConfig()->getValue('general/store_information/name');
        return !empty($result) ? $result : parent::getFromName();
    }

    private function getTransportFactory(): TransportInterfaceFactory
    {
        return Registry::getInstance()->get(TransportInterfaceFactory::class);
    }

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection Magento 2.4.7 classes that are removed.
     * @noinspection PhpUndefinedNamespaceInspection
     * @noinspection PhpUndefinedClassInspection
     */
    protected function send247(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ): Throwable|bool {
        try {
            $text = new \Laminas\Mime\Part($bodyText);
            $text->type = \Laminas\Mime\Mime::TYPE_TEXT;
            $text->charset = 'utf-8';
            $text->encoding = \Laminas\Mime\Mime::ENCODING_QUOTEDPRINTABLE;

            $html = new \Laminas\Mime\Part($bodyHtml);
            $html->type = \Laminas\Mime\Mime::TYPE_HTML;
            $html->charset = 'utf-8';
            $html->encoding = \Laminas\Mime\Mime::ENCODING_QUOTEDPRINTABLE;

            $body = (new \Laminas\Mime\Message())->setParts([$text, $html]);

            $mail = (new \Laminas\Mail\Message())
                ->setEncoding('UTF-8')
                ->setFrom($from, $fromName)
                ->addTo($to)
                ->setSubject($subject)
                ->setBody($body);

            (new \Laminas\Mail\Transport\Sendmail())->send($mail);
            return true;
        } catch (Throwable $e) {
            return $e;
        }
    }
}
