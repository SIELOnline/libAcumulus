<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusResponseException;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Fld;

/**
 * CheckAccount checks the account fields for being empty, incorrect, or correct
 */
class CheckAccount extends MessageCollection
{
    protected Acumulus $acumulusApiClient;
    protected Config $acumulusConfig;
    protected ?string $message;

    public function __construct(Acumulus $acumulusApiClient, Config $config, Translator $translator)
    {
        parent::__construct($translator);
        $this->acumulusApiClient = $acumulusApiClient;
        $this->acumulusConfig = $config;
        $this->message = null;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Checks the account settings for correctness and sufficient authorization.
     *
     * This is done by calling the 'About' API call and checking the result.
     *
     * @return string
     *   Message (translation key) to show in the form. Empty if successful.
     */
    public function doCheck(bool $useCache = true): string
    {
        if ($useCache) {
            $this->message ??= $this->acumulusConfig->getAccountMessage();
        }
        if ($this->message === null) {
            $this->messages = [];
            if ($this->emptyCredentials()) {
                // Start with filling in your account details.
                $this->message = 'message_auth_unknown';
            } else {
                try {
                    // Check if we can retrieve the about-info. This indicates if the account
                    // settings are correct.
                    $about = $this->acumulusApiClient->getAbout();
                    if ($about->getByCode(403) !== null) {
                        $this->message = 'message_error_auth';
                        $this->addFormMessage($this->t('message_error_auth_form'), Severity::Error, Fld::ContractCode);
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
                                $this->addFormMessage($this->t('message_warning_role_insufficient'), Severity::Warning, Fld::UserName);
                                break;
                            case Api::RoleApiManager:
                                $this->addFormMessage($this->t('message_warning_role_overkill'), Severity::Warning, Fld::UserName);
                                break;
                            default:
                                $this->message = 'message_error_role_deprecated';
                                $this->addFormMessage($this->t('message_error_role_deprecated'), Severity::Error, Fld::UserName);
                                break;
                        }
                    }
                } catch (AcumulusResponseException $e) {
                    $this->message = 'message_error_comm';
                    $this->addException($e);
                }
            }
            // "Cache" this value in the (local) config to prevent further API calls.
            $this->acumulusConfig->save(['cachedAccountMessage' => $this->message]);
        }
        return $this->message;
    }

    /**
     * Returns the Account fields status
     *
     * @param bool $returnMessage
     *   Whether to return the error message or false when the account settings are not
     *   correct/complete. Sometimes we just want to know whether the account settings
     *   are correct or not, sometimes we want to inform the user what is not correct.
     *   A second meaning of this parameter is whether to force a recheck or just use the
     *   cached status.
     *
     * @return null|bool|string
     *   - null: (some) credentials are empty
     *   - true: credentials are correct
     *   - false: credentials are incorrect: no message demanded
     *   - string: credentials are incorrect: error message
     *
     */
    public function getAccountStatus(bool $returnMessage = false): null|bool|string
    {
        // Current usage of $returnMessage happens to coincide with a parameter that
        // could be named $forceRecheck (and is thus the opposite of a parameter that
        // could be named $useCache): abuse and use for the other meanings.
        if ($returnMessage) {
            $this->message = null;
        }
        $message = $this->doCheck(!$returnMessage);
        return match ($message) {
            'message_auth_unknown' => null,
            '' => true,
            default => $returnMessage ? $message : false,
        };
    }

    /**
     * Returns whether (at least one of) the credentials are empty.
     */
    protected function emptyCredentials(): bool
    {
        $credentials = $this->acumulusConfig->getCredentials();
        return empty($credentials[Fld::ContractCode]) || empty($credentials[Fld::UserName]) || empty($credentials[Fld::Password]);
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
