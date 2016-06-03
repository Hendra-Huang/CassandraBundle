<?php

namespace CassandraBundle\Cassandra\ORM\Mapping;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Util\ClassUtils;

/**
 * The ClassMetadataFactory is used to create ClassMetadata objects that contain all the
 * metadata mapping information of a class which describes how a class should be mapped
 * to a relational database.
 */
class ClassMetadataFactory implements ClassMetadataFactoryInterface
{
    const ANNOTATION_CASSANDRA_TABLE_CLASS = \CassandraBundle\Cassandra\Mapping\Table::class;
    const ANNOTATION_CASSANDRA_COLUMN_CLASS = \CassandraBundle\Cassandra\Mapping\Column::class;

    /**
     * @var ClassMetadata[]
     */
    private $loadedMetadata = [];

    /**
     * Returns an array of all the loaded metadata currently in memory.
     *
     * @return ClassMetadata[]
     */
    public function getLoadedMetadata()
    {
        return $this->loadedMetadata;
    }

    /**
     * Loads the metadata of the class in question.
     *
     * @param string $name The name of the class for which the metadata should get loaded.
     *
     * @return array
     */
    protected function loadMetadata($name)
    {
        $classMetadata = $this->newClassMetadataInstance($name);
        $classMetadata->name = $name;
        $this->doLoadMetadata($classMetadata);
        $this->loadedMetadata[$name] = $classMetadata;

        return $classMetadata;
    }

    /**
     * Gets the class metadata descriptor for a class.
     *
     * @param string $className The name of the class.
     *
     * @return ClassMetadata
     *
     * @throws ReflectionException
     * @throws MappingException
     */
    public function getMetadataFor($className)
    {
        if (isset($this->loadedMetadata[$className])) {
            return $this->loadedMetadata[$className];
        }

        // Check for namespace alias
        if (strpos($className, ':') !== false) {
            list($namespaceAlias, $simpleClassName) = explode(':', $className, 2);

            $realClassName = $this->getFqcnFromAlias($namespaceAlias, $simpleClassName);
        } else {
            $realClassName = ClassUtils::getRealClass($className);
        }

        if (isset($this->loadedMetadata[$realClassName])) {
            // We do not have the alias name in the map, include it
            return $this->loadedMetadata[$className] = $this->loadedMetadata[$realClassName];
        }

        return $this->loadMetadata($realClassName);
    }

    /**
     * Checks whether the factory has the metadata for a class loaded already.
     *
     * @param string $className
     *
     * @return bool TRUE if the metadata of the class in question is already loaded, FALSE otherwise.
     */
    public function hasMetadataFor($className)
    {
        return isset($this->loadedMetadata[$className]);
    }

    /**
     * Sets the metadata descriptor for a specific class.
     *
     * NOTE: This is only useful in very special cases, like when generating proxy classes.
     *
     * @param string        $className
     * @param ClassMetadata $class
     */
    public function setMetadataFor($className, $class)
    {
        $this->loadedMetadata[$className] = $class;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLoadMetadata($classMetadata)
    {
        $values = [];
        $reflectionClass = new \ReflectionClass($classMetadata->name);

        // Save the entity mapping to metadata
        $classAnnotation = $this->reader->getClassAnnotation($reflectionClass, self::ANNOTATION_CASSANDRA_TABLE_CLASS);
        if ($classAnnotation) {
            $classMetadata->customRepositoryClassName = $classAnnotation->repositoryClass;
            $classMetadata->table['name'] = $classAnnotation->name ?: strtolower(preg_replace('/([^A-Z])([A-Z])/', '$1_$2', $reflectionClass->getShortName()));
        }

        // Save the field mapping to metadata
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnAnnotation = $this->reader->getPropertyAnnotation($reflectionProperty, self::ANNOTATION_CASSANDRA_COLUMN_CLASS);
            if ($columnAnnotation) {
                $classMetadata->fieldNames[] = $reflectionProperty->name;
                $classMetadata->fieldMappings[] = [
                    'fieldName' => $reflectionProperty->name,
                    'type' => $columnAnnotation->type,
                    'columnName' => $columnAnnotation->name,
                ];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function newClassMetadataInstance($className)
    {
        return new ClassMetadata($className);
    }

    /**
     * Gets the lower-case short name of a class.
     *
     * @param string $className
     *
     * @return string
     */
    private function getShortName($className)
    {
        if (strpos($className, '\\') === false) {
            return strtolower($className);
        }

        $parts = explode('\\', $className);

        return strtolower(end($parts));
    }

    /**
     * Return fully qualified class name.
     *
     * @TODO: Resolve fqcn for AcmeAppBundle to Acme\\AppBundle
     */
    protected function getFqcnFromAlias($namespaceAlias, $simpleClassName)
    {
        return $namespaceAlias.'\\'.$simpleClassName;
    }
}
