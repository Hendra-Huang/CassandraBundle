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
            foreach ($tableOptions as $optionName => $optionValue) {
                if ('compactStorage' === $optionName && false !== $optionValue) {
                    $tableOptionsParamCQL[] = 'COMPACT STORAGE';
                } elseif ('clusteringOrder' === $optionName && null !== $optionValue) {
                    $tableOptionsParamCQL[] = sprintf('CLUSTERING ORDER BY (%s)', $optionValue);
                } elseif (!\in_array($optionName, ['compactStorage', 'clusteringOrder'])) {
                    $tableOptionsParamCQL[] = $this->formatOption($optionName, $optionValue);
                }
            }

            if (!empty($tableOptionsParamCQL)) {
                $tableOptionsCQL = sprintf(' WITH %s', implode(' AND ', $tableOptionsParamCQL));
            }
        }

        return $this->_exec(sprintf('CREATE TABLE %s (%s%s)%s;', $name, implode(',', $fieldsWithType), $primaryKeyCQL, $tableOptionsCQL));
    }

    private function formatOption($optionName, $optionValue)
    {
        // numeric type : float or int
        if (\in_array($optionName, ['gc_grace_seconds' ,'memtable_flush_period_in_ms', 'default_time_to_live', 'bloom_filter_fp_chance'])) {
            if (!is_numeric($optionValue)) {
                throw SchemaException::wrongFormatForNumericTableOption($optionName, $optionValue);
            }

            return sprintf('%s = %s', $optionName, $optionValue);
        }
        // map type
        if (\in_array($optionName, ['compaction', 'compression', 'caching'])) {
            return sprintf('%s = %s', $optionName, $optionValue);
        }
        // string type
        return sprintf('%s = "%s"', $optionName, addslashes($optionValue));
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
