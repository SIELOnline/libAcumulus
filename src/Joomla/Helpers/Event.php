<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Siel\Acumulus\Helpers\Event as BaseEvent;
use Siel\Joomla\Component\Acumulus\Administrator\Extension\AcumulusComponent;

/**
 * Event implements {@see \Siel\Acumulus\Helpers\Event} for Joomla.
 */
class Event extends BaseEvent
{

    protected function getEventName(string $methodName): string
    {
        return str_replace('trigger', 'onAcumulus', $methodName);
    }

    protected function massageArgs(array $args): array
    {
        // \Joomla\CMS\Plugin\CMSPlugin::registerLegacyListener(), line 308:
        //   "Extract any old results; they must not be part of the method call."
        // Thus: a parameter with a name result is unset.
        if (isset($args['result'])) {
            $args['invoiceSendResult'] = $args['result'];
            unset($args['result']);
        }
        return $args;
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        PluginHelper::importPlugin('acumulus');
        $args['subject'] = $this->getAcumulusComponent();
        $event = AbstractEvent::create($eventName, $args);
        // @todo: in Joomla 6 interface CMSApplicationInterface will no longer extend
        //   EventAwareInterface. Replacement is not yet clear to me.
        $this->getCMSApplication()->getDispatcher()->dispatch($eventName, $event);
    }

    private function getAcumulusComponent(): AcumulusComponent
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getCMSApplication()->bootComponent('acumulus');
    }

    private function getCMSApplication(): CMSApplicationInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection  won't fail, application has started. */
        return Factory::getApplication();
    }
}
