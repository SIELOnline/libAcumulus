<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;

/**
 * Represents an emailAsPdf part of an Acumulus API invoice object.
 *
 * Field names are copied from the API, though capitals are introduced for
 * readability and to prevent PhpStorm typo inspections.
 *
 * Metadata can be added via the {@see MetadataCollection} methods.
 *
 * @property ?string $emailTo
 * @property ?string $emailBcc
 * @property ?string $emailFrom
 * @property ?string $subject
 * @property ?string $message
 * @property ?bool $confirmReading
 * @property ?bool $gfx
 *
 * @method bool setEmailTo(?string $value, int $mode = PropertySet::Always)
 * @method bool setEmailBcc(?string $value, int $mode = PropertySet::Always)
 * @method bool setEmailFrom(?string $value, int $mode = PropertySet::Always)
 * @method bool setSubject(?string $value, int $mode = PropertySet::Always)
 * @method bool setMessage(?string $value, int $mode = PropertySet::Always)
 * @method bool setConfirmReading(bool|int|null $value, int $mode = PropertySet::Always)
 * @method bool setGfx(bool|int|null $value, int $mode = PropertySet::Always)
 */
abstract class EmailAsPdf extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => 'emailTo', 'type' => 'string', 'required' => true],
            ['name' => 'emailBcc', 'type' => 'string'],
            ['name' => 'emailFrom', 'type' => 'string'],
            ['name' => 'subject', 'type' => 'string'],
            ['name' => 'message', 'type' => 'string'],
            ['name' => 'confirmReading', 'type' => 'bool', 'allowedValues' => [Api::ConfirmReading_No, Api::ConfirmReading_Yes]],
            ['name' => 'gfx', 'type' => 'bool', 'allowedValues' => [Api::ApplyGraphics_No, Api::ApplyGraphics_Yes]],
        ];
    }
}
