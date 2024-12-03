<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Meta;

/**
 * Wraps the {@see \Siel\Acumulus\Data\MetadataCollection} methods in methods
 * for an {@see \Siel\Acumulus\Data\AcumulusObject}.
 */
trait AcumulusObjectMetadataTrait
{
    private MetadataCollection $metadata;

    /**
     * Completes the shallow clone that PHP automatically performs.
     *
     * This implementation (deep) clones the {@see MetadataCollection}.
     */
    public function cloneMetadata(): void
    {
        if (isset($this->metadata)) {
            $this->metadata = clone $this->metadata;
        }
    }

    public function getMetadata(): MetadataCollection
    {
        $this->metadata ??= new MetadataCollection();
        return $this->metadata;
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::exists()}.
     */
    public function metadataExists(string $name): bool
    {
        return $this->getMetadata()->exists($name);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::get()}.
     */
    public function metadataGet(string $name): mixed
    {
        return $this->getMetadata()->get($name);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::remove()}.
     */
    public function metadataRemove(string $name): void
    {
        $this->getMetadata()->remove($name);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::set()}.
     */
    public function metadataSet(string $name, $value): void
    {
        $this->getMetadata()->set($name, $value);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::add()}.
     */
    public function metadataAdd(string $name, $value, bool $isList = true): void
    {
        $this->getMetadata()->add($name, $value, $isList);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::addMultiple()}.
     */
    public function metadataAddMultiple(string $name, array $values): void
    {
        $this->getMetadata()->addMultiple($name, $values);
    }

    /**
     * Returns the metadata as a keyed array.
     *
     * @return string[]
     *   The metadata as a keyed array.
     */
    protected function metadataToArray(): array
    {
        return $this->getMetadata()->toArray();
    }

    /**
     * Adds a warning to the object (in its metadata).
     *
     * @param string|\Siel\Acumulus\Helpers\Message $message
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
