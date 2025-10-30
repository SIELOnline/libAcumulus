<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Mail;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\Address;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\Exception\InvalidArgumentException;
use Magento\Framework\Mail\Message;
use Magento\Framework\Mail\MimeInterface;
use Magento\Framework\Mail\MimeMessage;
use Magento\Framework\Mail\MimeMessageInterface;
use Magento\Framework\Mail\MimePart;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address as SymfonyAddress;
use Symfony\Component\Mime\Message as SymfonyMessage;

use function is_array;

/**
 * Acumulus Magento email message: copied from Magento Framework Email message
 */
class AcumulusMagentoEmailMessage extends Message implements EmailMessageInterface
{
    private ?LoggerInterface $logger;
    protected Mailer $mailer;

    public function __construct(
        MimeMessageInterface $body,
        array $to,
        ?array $from = null,
        ?array $cc = null,
        ?array $bcc = null,
        ?array $replyTo = null,
        ?Address $sender = null,
        ?string $subject = '',
        ?string $encoding = 'utf-8',
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($encoding);
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
        $this->symfonyMessage = $body->getMimeMessage();
        $this->setBody($this->symfonyMessage);
        if (!empty($subject)) {
            $this->symfonyMessage->getHeaders()->addTextHeader('Subject', $subject);
        }

        $this->setSender($sender);
        $this->setRecipients($to, 'To');
        $this->setRecipients($replyTo, 'Reply-To');
        $this->setRecipients($from, 'From');
        $this->setRecipients($cc, 'Cc');
        $this->setRecipients($bcc, 'Bcc');
    }

    /**
     * Get Symfony Message
     *
     * @return SymfonyMessage
     */
    public function getSymfonyMessage(): SymfonyMessage
    {
        return $this->symfonyMessage;
    }

    /**
     * Set the sender of the email
     *
     * @param Address|null $sender
     */
    private function setSender(?Address $sender): void
    {
        if ($sender) {
            $this->symfonyMessage->getHeaders()->addMailboxHeader(
                'Sender',
                new SymfonyAddress($this->sanitiseEmail($sender->getEmail()), $sender->getName())
            );
        }
    }

    /**
     * Set recipients for the message
     *
     * @param array|null $addresses
     * @param string $method
     */
    private function setRecipients(?array $addresses, string $method): void
    {
        if ($method === 'to' && (empty($addresses) || count($addresses) < 1)) {
            throw new InvalidArgumentException('Email message must have at least one addressee');
        }

        if (!$addresses) {
            return;
        }

        $recipients = [];
        foreach ($addresses as $address) {
            try {
                if ($address instanceof Address) {
                    $recipients[] = new SymfonyAddress(
                        $this->sanitiseEmail($address->getEmail()),
                        $address->getName() ?? ''
                    );
                } elseif (is_array($address)) {
                    $recipients[] = new SymfonyAddress(
                        $this->sanitiseEmail($address['email']),
                        $address['name'] ?? ''
                    );
                } else {
                    $recipients[] = new SymfonyAddress($this->sanitiseEmail($address));
                }
            } catch (Exception $e) {
                $this->logger->warning(
                    'Could not add an invalid email address to the mailing queue',
                    ['exception' => $e]
                );
                continue;
            }
        }

        $this->symfonyMessage->getHeaders()->addMailboxListHeader($method, $recipients);
    }

    /**
     * @inheritDoc
     */
    public function getEncoding(): string
    {
        return $this->symfonyMessage->getHeaders()->getHeaderBody('Content-Transfer-Encoding');
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->symfonyMessage->getHeaders()->toArray();
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException
     */
    public function getFrom(): ?array
    {
        return $this->getAddresses('From');
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException
     */
    public function getTo(): array
    {
        return $this->getAddresses('To') ?? [];
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException
     */
    public function getCc(): ?array
    {
        return $this->getAddresses('Cc');
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException
     */
    public function getBcc(): ?array
    {
        return $this->getAddresses('Bcc');
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException
     */
    public function getReplyTo(): ?array
    {
        return $this->getAddresses('Reply-To');
    }

    /**
     * Get addresses from a header.
     *
     * @param string $headerName
     *
     * @return array|null
     */
    private function getAddresses(string $headerName): ?array
    {
        $header = $this->symfonyMessage->getHeaders()->get($headerName);
        if ($header) {
            return $this->convertAddressListToAddressArray($header->getAddresses());
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSender(): ?Address
    {
        $senderHeader = $this->symfonyMessage->getHeaders()->get('Sender');
        if (!$senderHeader) {
            return null;
        }

        $senderAddress = $senderHeader->getAddress();
        if (!$senderAddress) {
            return null;
        }

        return new Address($senderAddress->getAddress(), $senderAddress->getName());
    }

    /**
     * @inheritDoc
     */
    public function getMessageBody(): MimeMessageInterface
    {
        return new MimeMessage([
            new MimePart($this->getBodyHtml(), MimeInterface::TYPE_HTML),
            new MimePart($this->getBodyText(), MimeInterface::TYPE_TEXT),
        ]);
    }

    public function getBodyText(): string
    {
        return $this->symfonyMessage->getTextBody() ?? '';
    }

    public function getBodyHtml(): string
    {
        return $this->symfonyMessage->getHtmlBody() ?? '';
    }

    public function toString(): string
    {
        return $this->symfonyMessage->toString();
    }

    /**
     * Convert AddressList To Address Array
     *
     * @param array $addressList
     *
     * @return Address[]
     * @todo: array of what?
     *
     */
    private function convertAddressListToAddressArray(array $addressList): array
    {
        return array_map(function ($address) {
            return new Address($this->sanitiseEmail($address->getAddress()), $address->getName());
        }, $addressList);
    }

    /**
     * Sanitise email address
     *
     * @param ?string $email
     *
     * @return ?string
     * @throws InvalidArgumentException
     */
    private function sanitiseEmail(?string $email): ?string
    {
        if (!empty($email) && str_starts_with($email, '=?')) {
            $decodedValue = iconv_mime_decode($email, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (str_contains($decodedValue, ' ')) {
                throw new InvalidArgumentException('Invalid email format');
            }
        }

        return $email;
    }
}
