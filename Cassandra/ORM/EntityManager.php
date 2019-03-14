<?php

namespace CassandraBundle\Cassandra\ORM;

use Cassandra\BatchStatement;
use Cassandra\Session;
use Cassandra\Type;
use CassandraBundle\Cassandra\Connection;
use CassandraBundle\Cassandra\ORM\Mapping\ClassMetadata;
use CassandraBundle\Cassandra\ORM\Mapping\ClassMetadataFactoryInterface;
use CassandraBundle\Cassandra\ORM\Repository\DefaultRepositoryFactory;
use CassandraBundle\EventDispatcher\CassandraEvent;
use CassandraBundle\Cassandra\Utility\Type as CassandraType;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;

class EntityManager implements Session, EntityManagerInterface
{
    protected $connection;
    private $metadataFactory;
    private $logger;
    private $statements;
    private $repositoryFactory;
    private $schemaManager;

    const STATEMENT = 'statement';
    const ARGUMENTS = 'arguments';

    public function __construct(Connection $connection, ClassMetadataFactoryInterface $metadataFactory, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->metadataFactory = $metadataFactory;
        $this->schemaManager = new SchemaManager($connection);
        $this->repositoryFactory = new DefaultRepositoryFactory();
        $this->statements = [];
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getKeyspace()
    {
        return $this->connection->getKeyspace();
    }

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Gets the metadata factory used to gather the metadata of classes.
     *
     * @return \CassandraBundle\Cassandra\ORM\Mapping\ClassMetadataFactory
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * Returns the ORM metadata descriptor for a class.
     *
     * The class name must be the fully-qualified class name without a leading backslash
     * (as it is returned by get_class($obj)) or an aliased class name.
     *
     * Examples:
     * MyProject\Domain\User
     * sales:PriceRequest
     *
     * Internal note: Performance-sensitive method.
     *
     * @param string $className
     *
     * @return \CassandraBundle\Cassandra\ORM\Mapping\ClassMetadata
     */
    public function getClassMetadata($className)
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    /**
     * Gets the repository for an entity class.
     *
     * @param string $entityName the name of the entity
     *
     * @return \CassandraBundle\Cassandra\ORM\EntityRepository the repository class
     */
    public function getRepository($entityName)
    {
        return $this->repositoryFactory->getRepository($this, $entityName);
    }

    /**
     * Insert $entity to cassandra.
     *
     * @param object  $entity
     * @param Options $options |null
     */
    public function insert($entity, Options $options = null)
    {
        $metadata = $this->getClassMetadata(\get_class($entity));
        $tableName = $metadata->table['name'];
        $values = $this->readColumn($entity, $metadata);
        $columns = array_keys($values);

        $statement = sprintf(
            'INSERT INTO "%s"."%s" (%s) VALUES (%s)',
            $this->getKeyspace(),
            $tableName,
            implode(', ', $columns),
            implode(', ', array_map(function () {
                return '?';
            }, $columns))
        );
        $statement = $this->decorateInsertStatement($statement, $metadata, $options);

        $this->statements[] = [
            self::STATEMENT => $statement,
            self::ARGUMENTS => $values,
        ];
    }

    /**
     * add options to statement.
     *
     * @param $statement
     * @param ClassMetadata $metadata
     * @param Options|null  $options
     *
     * @return string
     */
    private function decorateInsertStatement($statement, ClassMetadata $metadata, Options $options = null)
    {
        if (!empty($options) && !empty($options->getIfNoExist())) {
            $statement .= ' IF NOT EXISTS ';
        } elseif (!empty($metadata->table['ifNoExist'])) {
            $statement .= ' IF NOT EXISTS ';
        }
        if (!empty($options) && !empty($options->getTtl())) {
            $statement .= ' USING TTL '.$options->getTtl();
        } elseif (!empty($metadata->table['defaultTtl'])) {
            $statement .= ' USING TTL '.$metadata->table['defaultTtl'];
        }

        return $statement;
    }

    /**
     * Update $entity to cassandra.
     *
     * @param object       $entity
     * @param Options|null $options
     *
     * @deprecated update method will be deprecated since version 1.3 and will be removed in 1.5
     */
    public function update($entity, Options $options = null)
    {
        return $this->insert($entity, $options);
    }

    /**
     * Delete $entity.
     *
     * @param object $entity
     */
    public function delete($entity)
    {
        $metadata = $this->getClassMetadata(\get_class($entity));
        $tableName = $metadata->table['name'];

        $statement = sprintf(
            'DELETE FROM "%s"."%s" WHERE id = ?',
            $this->getKeyspace(),
            $tableName
        );

        $this->statements[] = [
            self::STATEMENT => $statement,
            self::ARGUMENTS => ['id' => new \Cassandra\Uuid($entity->getId())],
        ];
    }

    /**
     * Execute batch process.
     */
    public function flush($async = true)
    {
        if (\count($this->statements)) {
            $this->logger->debug('CASSANDRA: BEGIN');
            $batch = new BatchStatement(\Cassandra::BATCH_LOGGED);

            foreach ($this->statements as $statement) {
                $this->logger->debug('CASSANDRA: '.$statement[self::STATEMENT].' => '.json_encode($statement[self::ARGUMENTS]));
                $batch->add($this->prepare($statement[self::STATEMENT]), $statement[self::ARGUMENTS]);
            }

            if ($async) {
                $this->executeAsync($batch);
            } else {
                $this->execute($batch);
            }
            $this->logger->debug('CASSANDRA: END');
            $this->statements = [];
        }
    }

    /**
     * Return values of all column in an $entity.
     *
     * @param object $entity
     *
     * @return []
     */
    private function readColumn($entity, $metadata)
    {
        foreach ($metadata->fieldMappings as $field) {
            $getterMethod = 'get'.ucfirst($field['fieldName']);
            if (null !== $entity->{$getterMethod}()) {
                if ($this->isCassandraType($entity->{$getterMethod}())) {
                    $values[$field['columnName']] = $entity->{$getterMethod}();
                } else {
                    $values[$field['columnName']] = $this->encodeColumnType($field['type'], $entity->{$getterMethod}());
                }
            }
        }

        return $values;
    }

    /**
     * Return bool if cassandra type.
     *
     * @param object $obj
     *
     * @return bool
     */
    private function isCassandraType($obj)
    {
        $classNames = ['\Cassandra\Collection', '\Cassandra\Custom', '\Cassandra\Map', '\Cassandra\Scalar', '\Cassandra\Set', '\Cassandra\Tuple', '\Cassandra\UserType'];
        foreach ($classNames as $className) {
            if (is_a($obj, $className)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return $value with appropriate $type.
     *
     * @param string $type
     * @param mixed  $value
     *
     * @return mixed
     */
    private function encodeColumnType($type, $value = null)
    {
        // Remove frozen keyword
        $type = preg_replace('/frozen\<(.+)\>/', '$1', $type);

        if (preg_match('/^set\<(.+)\>$/U', $type, $matches)) {
            $subType = trim($matches[1]);
            if (null !== $value) {
                $set = new \Cassandra\Set($this->encodeColumnType($subType));
                foreach ($value as $_value) {
                    $set->add($this->encodeColumnType($subType, $_value));
                }
            } else {
                $set = Type::set($this->encodeColumnType($subType));
            }

            return $set;
        }
        if (preg_match('/^map\<(.+),\ *(.+)\>$/U', $type, $matches)) {
            $keyType = trim($matches[1]);
            $valueType = trim($matches[2]);
            if (null !== $value) {
                $map = new \Cassandra\Map($this->encodeColumnType($keyType), $this->encodeColumnType($valueType));
                foreach ($value as $_key => $_value) {
                    $map->set($this->encodeColumnType($keyType, $_key), $this->encodeColumnType($valueType, $_value));
                }
            } else {
                $map = Type::map($this->encodeColumnType($keyType), $this->encodeColumnType($valueType));
            }

            return $map;
        }

        return CassandraType::transformToCassandraType($type, $value);
    }

    private function decodeColumnType($columnValue)
    {
        if (null === $columnValue) {
            return $columnValue;
        }

        try {
            if (\is_bool($columnValue)) {
                return $columnValue;
            }
            // Cassandra\Timestamp class
            if ($columnValue instanceof \Cassandra\Timestamp) {
                return $columnValue->time();
            }
            // Cassandra\Date class
            if ($columnValue instanceof \Cassandra\Date) {
                return $columnValue->seconds();
            }
            // Cassandra\Time class
            if ($columnValue instanceof \Cassandra\Time) {
                return $columnValue->seconds();
            }
            // Cassandra\Map class
            if ($columnValue instanceof \Cassandra\Map) {
                $decodedKeys = [];
                foreach ($columnValue->keys() as $key) {
                    $decodedKeys[] = $this->decodeColumnType($key);
                }
                $decodedValues = [];
                foreach ($columnValue->values() as $value) {
                    $decodedValues[] = $this->decodeColumnType($value);
                }

                return array_combine($decodedKeys, $decodedValues);
            }
            // Cassandra\Set class
            if ($columnValue instanceof \Cassandra\Set) {
                $decodedValues = [];
                foreach ($columnValue->values() as $value) {
                    $decodedValues[] = $this->decodeColumnType($value);
                }

                return $decodedValues;
            }

            return (string) $columnValue;
        } catch (\Exception $e) {
            return $columnValue->values();
        }

        return $columnValue;
    }

    public function cleanRow($cassandraRow)
    {
        $cleanRow = [];
        foreach ($cassandraRow as $name => $value) {
            $cleanRow[$name] = $this->decodeColumnType($value);
        }

        return $cleanRow;
    }

    /**
     * Finds an Entity by its identifier.
     *
     * @param ClassMetadata $metadata the metadata of the entity to find
     * @param mixed         $id       the identity of the entity to find
     *
     * @return object|null the entity instance or NULL if the entity can not be found
     */
    public function find(ClassMetadata $metadata, $id)
    {
        if ($id) {
            $cql = sprintf('SELECT * FROM %s WHERE id = ?', $metadata->table['name']);
            $query = $this->createQuery($metadata, $cql);
            try {
                $query->addParameter($id, 'uuid');
            } catch (\Cassandra\Exception\InvalidArgumentException $e) {
                $this->logger->error('CASSANDRA: '.$e->getMessage());

                return;
            }

            return $query->getOneOrNullResult();
        }

        return;
    }

    /**
     * Finds all entities.
     *
     * @param ClassMetadata $metadata the metadata of the entity to find
     *
     * @return ArrayCollection the array of entity instance or empty array if the entity can not be found
     */
    public function findAll(ClassMetadata $metadata)
    {
        $cql = sprintf('SELECT * FROM %s', $metadata->table['name']);
        $query = $this->createQuery($metadata, $cql);

        return $query->getResult();
    }

    public function prepareArguments($arguments)
    {
        return [self::ARGUMENTS => $arguments];
    }

    /**
     * {@inheritdoc}
     */
    public function execute($statement, $options = null)
    {
        return $this->connection->execute($statement, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function executeAsync($statement, $options = null)
    {
        return $this->connection->executeAsync($statement, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($cql, $options = null)
    {
        return $this->connection->prepare($cql, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareAsync($cql, $options = null)
    {
        return $this->connection->prepareAsync($cql, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function close($timeout = null)
    {
        return $this->connection->close($timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function closeAsync()
    {
        return $this->connection->closeAsync();
    }

    /**
     * {@inheritdoc}
     */
    public function schema()
    {
        return $this->connection->schema();
    }

    /**
     * {@inheritdoc}
     */
    public function metrics()
    {
        return $this->connection->metrics();
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareResponse($response, CassandraEvent $event = null)
    {
        return $this->connection->prepareResponse($response, $event);
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareEvent($command, array $args)
    {
        return $this->connection->prepareEvent($command, $args);
    }

    /**
     * {@inheritdoc}
     */
    protected function send($command, array $arguments)
    {
        return $this->connection->send($command, $arguments);
    }

    public function newHydrator(ClassMetadata $metadata, $hydrationMode)
    {
        switch ($hydrationMode) {
            case Query::HYDRATE_OBJECT:
                return new \CassandraBundle\Cassandra\ORM\Hydration\ObjectHydrator($metadata);

            case Query::HYDRATE_ARRAY:
                return new \CassandraBundle\Cassandra\ORM\Hydration\ArrayHydrator($metadata);
        }

        throw ORMException::invalidHydrationMode($hydrationMode);
    }

    public function createQuery(ClassMetadata $metadata, $cql = '')
    {
        $query = new Query($this);
        $query->setMetadata($metadata);
        if (!empty($cql)) {
            $query->setCql($cql);
        }

        return $query;
    }
}
