<?php

namespace CassandraBundle\Cassandra\ORM;

use CassandraBundle\Cassandra\Connection;

class SchemaManager
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    private function _exec($cql)
    {
        $statement = $this->connection->prepare($cql);
        $this->connection->execute($statement);
    }

    public function createTable($name, $fields, $primaryKeyFields = [], $tableOptions = [])
    {
        $fieldsWithType = array_map(function ($field) {
            return $field['columnName'] . ' ' . $field['type'];
        }, $fields);
        $primaryKeyCQL = $tableOptionsCQL = '';
        if (count($primaryKeyFields) > 0) {
            $partitionKey = $primaryKeyFields[0];
            // if there is composite partition key
            if (is_array($partitionKey) && count($partitionKey) > 1) {
                $primaryKeyFields[0] = sprintf('(%s)', implode(',', $partitionKey));
            }
            $primaryKeyCQL = sprintf(',PRIMARY KEY (%s)', implode(',', $primaryKeyFields));
        }

        if (!empty($tableOptions)) {
            $tableOptionsParamCQL = [];
            if (isset($tableOptions['compactStorage']) && false !== $tableOptions['compactStorage']) {
                $tableOptionsParamCQL[] = 'COMPACT STORAGE';
            }

            if (isset($tableOptions['clusteringOrder']) && !is_null($tableOptions['clusteringOrder'])) {
                $tableOptionsParamCQL[] = sprintf('CLUSTERING ORDER BY (%s)', $tableOptions['clusteringOrder']);
            }

            if (!empty($tableOptionsParamCQL)) {
                $tableOptionsCQL = sprintf(' WITH %s', implode(' AND ', $tableOptionsParamCQL));
            }
        }

        $this->_exec(sprintf('CREATE TABLE %s (%s%s)%s;', $name, implode(',', $fieldsWithType), $primaryKeyCQL, $tableOptionsCQL));
    }

    public function dropTable($name)
    {
        $this->_exec(sprintf('DROP TABLE IF EXISTS %s', $name));
    }

    public function createIndexes($tableName, $indexes)
    {
        foreach ($indexes as $index) {
            $this->createIndex($tableName, $index);
        }
    }

    public function createIndex($tableName, $index)
    {
        $this->_exec(sprintf('CREATE INDEX ON %s (%s)', $tableName, $index));
    }
}
