<?php

namespace CassandraBundle\Tests\Units\Cassandra\ORM;

use mageekguy\atoum\test;
use CassandraBundle\Cassandra\ORM\SchemaManager as TestedSchemaManager;

class SchemaManager extends test
{
    /**
     * @dataProvider tableConfigProvider
     */
    public function testICreateTable($tableName, $fields, $primaryKeys, $tableOptions, $expectedCQL)
    {
        $this
            ->and($connectionMock = $this->getConnectionMock())
            ->and($clusterMock = $this->getClusterMock())
            ->and($sessionMock = $this->getSessionMock())
            ->and($clusterMock->getMockController()->connect = $sessionMock)
            ->and($connectionMock->setCluster($clusterMock))
            ->given($testedClass = new TestedSchemaManager($connectionMock))
            ->if($testedClass->createTable($tableName, $fields, $primaryKeys, $tableOptions))
            ->mock($connectionMock)
            ->call('prepare')
            ->withIdenticalArguments($expectedCQL)->once()
            ->call('execute')
            ->once();
    }

    /**
     * @dataProvider tableConfigProvider
     */
    public function testIWontCreateTableWhenDumpCQL($tableName, $fields, $primaryKeys, $tableOptions, $expectedCQL)
    {
        $this
            ->and($connectionMock = $this->getConnectionMock())
            ->and($clusterMock = $this->getClusterMock())
            ->and($sessionMock = $this->getSessionMock())
            ->and($clusterMock->getMockController()->connect = $sessionMock)
            ->and($connectionMock->setCluster($clusterMock))
            ->given($testedClass = new TestedSchemaManager($connectionMock))
            ->if($testedClass->forceDumpCql(true))
            ->and($testedClass->createTable($tableName, $fields, $primaryKeys, $tableOptions))
            ->mock($connectionMock)
            ->call('prepare')
            ->never()
            ->call('execute')
            ->never();
    }

    protected function tableConfigProvider()
    {
        return [
            [
                'test',
                [
                    ['columnName' => 'id', 'type' => 'uuid'],
                    ['columnName' => 'name', 'type' => 'text'],
                ],
                ['id'],
                ['compactStorage' => true],
                'CREATE TABLE test (id uuid,name text,PRIMARY KEY (id)) WITH COMPACT STORAGE;',
            ],
            [
                'test',
                [
                    ['columnName' => 'id', 'type' => 'uuid'],
                    ['columnName' => 'name', 'type' => 'text'],
                    ['columnName' => 'lastname', 'type' => 'text'],
                    ['columnName' => 'date', 'type' => 'timestamp'],
                ],
                ['id', 'date'],
                [],
                'CREATE TABLE test (id uuid,name text,lastname text,date timestamp,PRIMARY KEY (id,date));',
            ],
            [
                'test',
                [
                    ['columnName' => 'id', 'type' => 'uuid'],
                    ['columnName' => 'name', 'type' => 'text'],
                    ['columnName' => 'lastname', 'type' => 'text'],
                    ['columnName' => 'date', 'type' => 'timestamp'],
                ],
                ['id', 'date'],
                ['clusteringOrder' => 'date DESC'],
                'CREATE TABLE test (id uuid,name text,lastname text,date timestamp,PRIMARY KEY (id,date)) WITH CLUSTERING ORDER BY (date DESC);',
            ],
            [
                'test',
                [
                    ['columnName' => 'id', 'type' => 'uuid'],
                    ['columnName' => 'name', 'type' => 'text'],
                    ['columnName' => 'lastname', 'type' => 'text'],
                    ['columnName' => 'date', 'type' => 'timestamp'],
                ],
                ['id', 'date'],
                ['compactStorage' => true, 'clusteringOrder' => 'date DESC'],
                'CREATE TABLE test (id uuid,name text,lastname text,date timestamp,PRIMARY KEY (id,date)) WITH COMPACT STORAGE AND CLUSTERING ORDER BY (date DESC);',
            ],
        ];
    }

    private function getConnectionMock()
    {
        $mockConnection = new \mock\CassandraBundle\Cassandra\Connection(
            [
                'keyspace' => 'test',
                'hosts' => ['127.0.0.1'],
                'user' => '',
                'password' => '',
                'retries' => ['sync_requests' => 1],
            ]
        );

        return $mockConnection;
    }

    private function getClusterMock()
    {
        $this->getMockGenerator()->shuntParentClassCalls();

        return new \mock\Cassandra\Cluster();
    }

    private function getSessionMock($retry = 0, $error = false)
    {
        $this->getMockGenerator()->shuntParentClassCalls();

        $session = new \mock\Cassandra\Session();
        $session->getMockController()->executeAsync = new \mock\Cassandra\Future();
        $session->getMockController()->prepareAsync = new \mock\Cassandra\Future();

        $session->getMockController()->execute = function () use (&$retry, $error) {
            if (($error && $retry <= 0) || ($retry > 0)) {
                --$retry;
                throw new \Cassandra\Exception\RuntimeException('runtime error');
            }
        };

        return $session;
    }
}
