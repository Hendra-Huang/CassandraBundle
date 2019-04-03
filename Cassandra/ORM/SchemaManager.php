<?php

namespace CassandraBundle\Cassandra\ORM;

use CassandraBundle\Cassandra\Connection;

class SchemaManager
{
    protected $connection;

    /** @var bool */
    private $dumpCql = false;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $cql
     *
     * @return string
     */
    private function _exec($cql)
    {
        if (!$this->dumpCql) {
            $statement = $this->connection->prepare($cql);
            $this->connection->execute($statement);
        }

        return $cql;
    }

    /**
     * @param bool $dumpCql
     */
    public function forceDumpCql($dumpCql)
    {
        $this->dumpCql = $dumpCql;
    }

    /**
     * @param string $name
     * @param array  $fields
     * @param array  $primaryKeyFields
     * @param array  $tableOptions
     *
     * @return string
     */
    public function createTable($name, $fields, $primaryKeyFields = [], $tableOptions = [])
    {
        $fieldsWithType = array_map(function ($field) {
            return $field['columnName'].' '.$field['type'];
        }, $fields);
        $primaryKeyCQL = $tableOptionsCQL = '';
        if (\count($primaryKeyFields) > 0) {
            $partitionKey = $primaryKeyFields[0];
            // if there is composite partition key
            if (\is_array($partitionKey) && \count($partitionKey) > 1) {
                $primaryKeyFields[0] = sprintf('(%s)', implode(',', $partitionKey));
            }
            $primaryKeyCQL = sprintf(',PRIMARY KEY (%s)', implode(',', $primaryKeyFields));
        }

        if (!empty($tableOptions)) {
            $tableOptionsParamCQL = [];
            if (isset($tableOptions['compactStorage']) && false !== $tableOptions['compactStorage']) {
                $tableOptionsParamCQL[] = 'COMPACT STORAGE';
            }

            if (isset($tableOptions['clusteringOrder']) && null !== $tableOptions['clusteringOrder']) {
                $tableOptionsParamCQL[] = sprintf('CLUSTERING ORDER BY (%s)', $tableOptions['clusteringOrder']);
            }

            if (!empty($tableOptionsParamCQL)) {
                $tableOptionsCQL = sprintf(' WITH %s', implode(' AND ', $tableOptionsParamCQL));
            }
        }

        return $this->_exec(sprintf('CREATE TABLE %s (%s%s)%s;', $name, implode(',', $fieldsWithType), $primaryKeyCQL, $tableOptionsCQL));
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function dropTable($name)
    {
        return $this->_exec(sprintf('DROP TABLE IF EXISTS %s;', $name));
    }

    /**
     * @param string $tableName
     * @param array  $indexes
     *
     * @return string
     */
    public function createIndexes($tableName, $indexes)
    {
        $indexesCql = [];
        foreach ($indexes as $index) {
            $indexesCql[] = $this->createIndex($tableName, $index);
        }

        return implode(PHP_EOL, $indexesCql);
    }

    /**
     * @param string $tableName
     * @param string $index
     *
     * @return string
     */
    private function createIndex($tableName, $index)
    {
        return $this->_exec(sprintf('CREATE INDEX ON %s (%s);', $tableName, $index));
    }
}
