<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\CheckAccount;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

use function sprintf;

/**
 * Shows a message with 2 buttons 'later' and 'hide' (and the Acumulus logo).
 *
 * This form is configurable regarding the message to show, where to store the 'later'
 * delay and how long that delay should be.
 *
 * SECURITY REMARKS
 * ----------------
 * The only user-provided value is the name of the button clicked on POST and that is
 * sanitised if it gets printed in an error message if not recognised (which means faulty
 * user input). For the rest it is only compared to hardcoded values.
 *
 * @noinspection PhpUnused
 */
class MessageForm extends Form
{
    protected string $action = '';
    protected string $configKey = '';
    protected string $message = '';
    protected int $later = 7;

    public function __construct(
        Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        CheckAccount $checkAccount,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct($acumulusApiClient, $formHelper, $checkAccount, $shopCapabilities, $config, $environment, $translator, $log);
        $this->addMeta = false;
        $this->isFullPage = false;
        $this->addSeverityClassToFields = false;
        $this->translator->add(new MessageFormTranslations());
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    /**
     * Defines which config key should be used to store the timestamp that indicates when
     * the message should be shown again.
     *
     * @return $this
     */
    public function setConfigKey(string $configKey): static
    {
        $this->configKey = $configKey;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Sets the message that will be played to the user.
     *
     * Should be translated, as passing in a translation key might need a call to
     * sprintf() with additional arguments, which is difficult to specify in this method.
     *
     * @return $this
     */
    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getLater(): int
    {
        return $this->later;
    }

    /**
     * Set the number of days to wait before showing the message again after the user
     * clicked the 'later' button.
     *
     * @return $this
     */
    public function setLater(int $later): static
    {
        $this->later = $later;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * This override handles the case that this message is displayed on another
     * Acumulus form and the post thus is meant for that form not this one.
     */
    public function isSubmitted(): bool
    {
        return parent::isSubmitted() && isset($_POST['clicked']);
    }

    /**
     * @inheritDoc
     *
     * This override adds sanitation to the values and already combines some
     * values to retrieve a Source object
     */
    protected function setSubmittedValues(): void
    {
        parent::setSubmittedValues();

        // Sanitise service: lowercase ASCII.
        $this->submittedValues['service'] = preg_replace('/[^a-z]/', '', $this->submittedValues['clicked']);
        $this->submittedValues['messageName'] = preg_replace('/[^a-zA-Z0-9]/', '', $this->submittedValues['clicked']);
    }

    /**
     * {@inheritdoc}
     *
     * Performs the given action for the posted message form.
     */
    protected function execute(): bool
    {
        $this->action = $this->getSubmittedValue('service');
        $this->setConfigKey($this->getSubmittedValue('messageName'))
            ->setLater((int) $this->getSubmittedValue('laterDays'));
        switch ($this->action) {
            case 'later':
                $result = $this->acumulusConfig->save([$this->getConfigKey() => time() + $this->getLater() * 24 * 60 * 60]);
                break;
            case 'hide':
                $result = $this->acumulusConfig->save([$this->getConfigKey() => PHP_INT_MAX]);
                break;
            default:
                $this->createAndAddMessage(sprintf($this->t('unknown_action'), $this->action), Severity::Error);
                $result = false;
                break;
        }
        return $result;
    }

    protected function getFieldDefinitions(): array
    {
        $fields = match ($this->action) {
            'later', 'hide' => $this->getOkFields(),
            default => $this->getFullFields(),
        };
        return [
            'message' => [
                'type' => 'fieldset',
                'legend' => $this->getLogo(100),
                'fields' => $fields,
            ],
        ];
    }

    /**
     * Returns the field definitions when we are showing the full message.
     *
     * @return array[];
     *
     * @todo: placing the logo in the legend worked well for PrestaShop (new release
     *   message), but might not work for others: to test.
     */
    protected function getFullFields(): array
    {
        return [
//            'logo' => [
//                'type' => 'markup',
//                'value' => $this->getLogo(100),
//            ],
            'message' => [
                'type' => 'markup',
                'value' => $this->getMessage(),
            ],
            'messageName' => [
                'type' => 'hidden',
                'value' => $this->getConfigKey(),
            ],
            'laterDays' => [
                'type' => 'hidden',
                'value' => $this->getLater(),
            ],
            'later' => [
                'type' => 'button',
                'value' => $this->t('later'),
                'attributes' => [
                    'class' => 'acumulus-ajax',
                ],
            ],
            'hide' => [
                'type' => 'button',
                'value' => $this->t('hide'),
                'attributes' => [
                    'class' => 'acumulus-ajax',
                ],
            ],
        ];
    }

    /**
     * Description.
     *
     *
     * @return array[]
     *   Description.
     */
    private function getOkFields(): array
    {
        return [
            $this->action => [
                'type' => 'markup',
                'value' => $this->t('no_problem'),
            ],
        ];
    }
}
