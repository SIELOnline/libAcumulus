<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusResponseException;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;

/**
 * CheckAccount does foo.
 */
class CheckAccount extends MessageCollection
{
    protected Acumulus $acumulusApiClient;
    protected Config $acumulusConfig;
    protected string $message;

    public function __construct(Acumulus $acumulusApiClient, Config $config, Translator $translator)
    {
        parent::__construct($translator);
        $this->acumulusApiClient = $acumulusApiClient;
        $this->acumulusConfig = $config;
    }

    /**
     * Checks the account settings for correctness and sufficient authorization.
     *
     * This is done by calling the 'About' API call and checking the result.
     *
     * @return string
     *   Message to show in the form. Empty if successful.
     */
    public function doCheck(bool $force = false): string
    {
        if (!isset($this->message) || $force) {
            unset($this->message);
            $this->messages = [];
            // Check if we can retrieve a picklist. This indicates if the account settings are
            // correct.
            if ($this->emptyCredentials()) {
                // First fill in your account details.
                $this->message = 'message_auth_unknown';
            } else {
                try {
                    $about = $this->acumulusApiClient->getAbout();
                    if ($about->getByCode(403) !== null) {
                        $this->message = 'message_error_auth';
                        $this->addFormMessage($this->t('message_error_auth_form'), Severity::Error, Tag::ContractCode);
                    } elseif ($about->hasError()) {
                        $this->message = 'message_error_comm';
                        $this->addMessages($about->getMessages(Severity::WarningOrWorse));
                    } else {
                        // Check role.
                        $this->message = '';
                        $response = $about->getMainAcumulusResponse();
                        $roleId = (int) $response['roleid'];
                        switch ($roleId) {
                            case Api::RoleApiUser:
                                // Correct role: no additional message.
                                break;
                            case Api::RoleApiCreator:
                                $this->addFormMessage($this->t('message_warning_role_insufficient'), Severity::Warning, Tag::UserName);
                                break;
                            case Api::RoleApiManager:
                                $this->addFormMessage($this->t('message_warning_role_overkill'), Severity::Warning, Tag::UserName);
                                break;
                            default:
                                $this->message = 'message_error_role_deprecated';
                                $this->addFormMessage($this->t('message_error_role_deprecated'), Severity::Error, Tag::UserName);
                                break;
                        }
                    }
                } catch (AcumulusResponseException $e) {
                    $this->message = 'message_error_comm';
                    $this->addException($e);
                }
            }
        }
        return $this->message;
    }

    /**
     * Returns whether (at least one of) the credentials are (is) empty.
     */
    protected function emptyCredentials(): bool
    {
        $credentials = $this->acumulusConfig->getCredentials();
        return empty($credentials[Tag::ContractCode]) || empty($credentials[Tag::UserName]) || empty($credentials[Tag::Password]);
    }

    /**
     * Adds a form message.
     *
     * @param string $field
     *   The id of the form field. Does not have to be specified if multiple
     *   fields are involved
     */
    protected function addFormMessage(string $message, int $severity, string $field = ''): void
    {
        $this->addMessage(Message::createForFormField($message, $severity, $field));
    }
}
