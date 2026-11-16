<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTimeInterface;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Meta;

/**
 * Wraps the {@see MetadataCollection} methods in methods for an {@see AcumulusObject}
 * (or any ther object, that wants to contain a MetadataCollection).
 */
trait MetadataTrait
{
    private MetadataCollection $metadata;

    protected function getMetadataCollection(): MetadataCollection
    {
        $this->metadata ??= new MetadataCollection();
        return $this->metadata;
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::exists()}.
     */
    public function metadataExists(string $name): bool
    {
        return $this->getMetadataCollection()->exists($name);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::get()}.
     */
    public function metadataGet(string $name): mixed
    {
        return $this->getMetadataCollection()->get($name);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::remove()}.
     */
    public function metadataRemove(string $name): void
    {
        $this->getMetadataCollection()->remove($name);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::set()}.
     */
    public function metadataSet(string $name, mixed $value, int $behaviour = MetadataValue::Unique): void
    {
        $value = $this->handleDate($name, $value);
        $this->getMetadataCollection()->set($name, $value, $behaviour);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::add()}.
     */
    public function metadataAdd(string $name, $value, int $behaviour = MetadataValue::UniqueList): void
    {
        $value = $this->handleDate($name, $value);
        $this->getMetadataCollection()->add($name, $value, $behaviour);
    }

    protected function handleDate(string $name, mixed $value): mixed
    {
        if (str_contains($name, 'date') && !str_contains($name, 'time') && $value instanceof DateTimeInterface) {
            $value = $value->setTime(0, 0, 0);
        }
        return $value;
    }

    /**
     * Creates ad deep clone of the metadata value with the given name from the given
     * AcumulusObject and adds it to this metadata collection.
     *
     * If no metadata value exists with the given name in the given AcumulusObject nothing
     * is done.
     */
    public function metadataCopy(self $source, string $name): void
    {
        /**
         * @noinspection PhpParamsInspection  Expected parameter of type 'MetadataCollection', 'MetadataTrait' provided:
         *   Strange as Traits can never be used as classes/types.
         */
        $this->getMetadataCollection()->copy($source, $name);
    }

    /**
     * Returns the metadata as a keyed array.
     *
     * @return string[]
     *   The metadata as a keyed array.
     */
    protected function metadataToArray(): array
    {
        return $this->getMetadataCollection()->toArray();
    }

    /**
     * Adds a message to the object (in its metadata).
     *
     * To detect duplicates, we convert a {@see Message} to a string before adding it.
     */
    public function addMessage(string|Message $message, int $severity = Severity::Warning): void
    {
        $metaNames = [
            Severity::Exception => Meta::Exception,
            Severity::Error => Meta::Error,
            Severity::Warning => Meta::Warning,
            Severity::Notice => Meta::Notice,
            Severity::Info => Meta::Info,
            Severity::Success => Meta::Success,
            Severity::Log => Meta::Debug,
        ];
        if ($message instanceof Message) {
            $severity = $message->getSeverity();
        }
        $severity = $metaNames[$severity] ?? Meta::Warning;
        $this->metadataAdd($severity, (string) $message, MetadataValue::Unique);
    }

    /**
     * Returns whether the object contains a warning (in its metadata).
     *
     * As this method is intended to indicate the existence of a warning at any
     * level, this method should be overridden for those data objects that have
     * child data objects.
     *
     * @return bool
     *   True if the object, or one of its children, contains a warning in its
     *   metadata, false otherwise.
     */
    public function hasWarning(): bool
    {
        return $this->metadataExists(Meta::Warning);
    }
}
