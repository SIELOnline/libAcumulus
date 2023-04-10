<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Meta;

/**
 * Wraps the {@see \Siel\Acumulus\Data\MetadataCollection} methods in methods
 * for an {@see \Siel\Acumulus\Data\AcumulusObject}.
 */
trait AcumulusObjectMetadataTrait
{
    private MetadataCollection $metadata;

    public function getMetadata(): MetadataCollection
    {
        $this->metadata ??= new MetadataCollection();
        return $this->metadata;
    }

    /**
     * {@see \Siel\Acumulus\Data\MetadataCollection::exists()}.
     */
    public function metadataExists(string $name): bool
    {
        return $this->getMetadata()->exists($name);
    }

    /**
     * {@see \Siel\Acumulus\Data\MetadataCollection::get()}.
     */
    public function metadataGet(string $name)
    {
        return $this->getMetadata()->get($name);
    }

    /**
     * {@see \Siel\Acumulus\Data\MetadataCollection::remove()}.
     */
    public function metadataRemove(string $name): void
    {
        $this->getMetadata()->remove($name);
    }

    /**
     * {@see \Siel\Acumulus\Data\MetadataCollection::set()}.
     */
    public function metadataSet(string $name, $value): void
    {
        $this->getMetadata()->set($name, $value);
    }

    /**
     * {@see \Siel\Acumulus\Data\MetadataCollection::add()}.
     */
    public function metadataAdd(string $name, $value): void
    {
        $this->getMetadata()->add($name, $value);
    }

    /**
     * Returns whether the object contains a warning (in its metadata).
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
