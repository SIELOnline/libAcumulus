<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Meta;

/**
 * Wraps the {@see \Siel\Acumulus\Data\MetadataCollection} methods in methods
 * for an {@see \Siel\Acumulus\Data\AcumulusObject}, or any ther object, that wants to
 * contain a MetadataCollection.
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
    public function metadataSet(string $name, $value): void
    {
        $this->getMetadataCollection()->set($name, $value);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::add()}.
     */
    public function metadataAdd(string $name, $value, bool $isList = true): void
    {
        $this->getMetadataCollection()->add($name, $value, $isList);
    }

    /**
     * Creates ad deep clone of the metadata value with the given name form the given
     * AcumulusObject and adds it to this metadata collection.
     *
     * If no metadata value exists with the given name in the given AcumulusObject nothing
     * is done.
     */
    public function metadataCopy(AcumulusObject $source, string $name): void
    {
        if ($source->metadataExists($name)) {
            $this->getMetadataCollection()->set($name, $source->getMetadataCollection()->copy($name));
        }
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
     * Adds a warning to the object (in its metadata).
     */
    public function addWarning(string|Message $message): void
    {
        $this->metadataAdd(Meta::Warning, $message, false);
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
