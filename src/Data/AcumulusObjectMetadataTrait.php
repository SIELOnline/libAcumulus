<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Meta;

use function assert;
use function is_string;

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
     * See {@see \Siel\Acumulus\Data\MetadataCollection::exists()}.
     */
    public function metadataExists(string $name): bool
    {
        return $this->getMetadata()->exists($name);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::get()}.
     */
    public function metadataGet(string $name)
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
     * See {@see \Siel\Acumulus\Data\MetadataCollection::setMultiple()}.
     * @deprecated
     */
    public function metadataSetMultiple(array $values): void
    {
        $this->getMetadata()->setMultiple($values);
    }

    /**
     * See {@see \Siel\Acumulus\Data\MetadataCollection::add()}.
     */
    public function metadataAdd(string $name, $value, bool $isList = true): void
    {
        $this->getMetadata()->add($name, $value, $isList);
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
        assert(is_string($message) || $message instanceof Message);
        $this->metadataAdd(Meta::Warning, $message);
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
