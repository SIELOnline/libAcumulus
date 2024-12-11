<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Contains translations for classes in the \Siel\Acumulus\ApiClient namespace.
 */
class SeverityTranslations extends TranslationCollection
{
    protected array $nl = [
        'severity_' . Severity::Exception => 'Ernstige fout',
        'severity_' . Severity::Error => 'Fout',
        'severity_' . Severity::Warning => 'Waarschuwing',
        'severity_' . Severity::Notice => 'Opmerking',
        'severity_' . Severity::Info => 'Informatie',
        'severity_' . Severity::Log => 'Debug',
        'severity_' . Severity::Success => 'Succes',
        'severity_' . Severity::Unknown => 'Nog niet gezet',
        'severity_unknown' => 'Onbekend bericht-ernstniveau %d',
    ];

    protected array $en = [
        'severity_' . Severity::Exception => 'Exception',
        'severity_' . Severity::Error => 'Error',
        'severity_' . Severity::Warning => 'Warning',
        'severity_' . Severity::Notice => 'Notice',
        'severity_' . Severity::Info => 'Info',
        'severity_' . Severity::Log => 'Debug',
        'severity_' . Severity::Success => 'Success',
        'severity_' . Severity::Unknown => 'Not yet set',
        'severity_unknown' => 'Unknown severity level %d',
    ];
}
