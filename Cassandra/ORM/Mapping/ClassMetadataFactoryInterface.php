<?php

namespace CassandraBundle\Cassandra\ORM\Mapping;

/**
 * Contract for a Doctrine persistence layer ClassMetadata class to implement.
 */
interface ClassMetadataFactoryInterface
{
    /**
     * Gets the class metadata descriptor for a class.
     *
     * @param string $className the name of the class
     *
     * @return ClassMetadata
     */
    public function getMetadataFor($className);

    /**
     * Checks whether the factory has the metadata for a class loaded already.
     *
     * @param string $className
     *
     * @return bool TRUE if the metadata of the class in question is already loaded, FALSE otherwise
     */
    public function hasMetadataFor($className);

    /**
     * Sets the metadata descriptor for a specific class.
     *
     * @param string        $className
     * @param ClassMetadata $class
     */
    public function setMetadataFor($className, $class);
}
