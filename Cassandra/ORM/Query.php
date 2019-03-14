<?php

namespace CassandraBundle\Cassandra\ORM;

use CassandraBundle\Cassandra\Utility\Type as CassandraType;

class Query
{
    /**
     * Hydrates an object graph. This is the default behavior.
     */
    const HYDRATE_OBJECT = 1;

    /**
     * Hydrates an array graph.
     */
    const HYDRATE_ARRAY = 2;

    private $_em;

    private $metadata;

    private $cql;

    private $parameters;

    private $hydrationMode;

    /**
     * @param \CassandraBundle\Cassandra\ORM\EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->_em = $em;
        $this->cql = null;
        $this->parameters = [];
        $this->hydrationMode = self::HYDRATE_OBJECT;
    }

    public function setCql($cql)
    {
        $this->cql = $cql;

        return $this;
    }

    public function getCql($cql)
    {
        return $this->cql;
    }

    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getMetadata($metadata)
    {
        return $this->metadata;
    }

    public function addParameter($value, $type = null)
    {
        if ($type) {
            $this->parameters[] = CassandraType::transformToCassandraType($type, $value);
        } else {
            $this->parameters[] = $value;
        }

        return $this;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function execute()
    {
        $statement = $this->_em->prepare($this->cql);
        $arguments = null;
        if (\count($this->parameters) > 0) {
            $arguments = $this->_em->prepareArguments($this->parameters);
        }

        $logger = $this->_em->getLogger();
        $logger->debug('CASSANDRA: '.$this->cql.' => '.json_encode($this->parameters));

        return $this->_em->execute($statement, $arguments);
    }

    public function getOneOrNullResult($hydrationMode = self::HYDRATE_OBJECT)
    {
        $result = $this->execute();
        if ($result && $data = $result->first()) {
            $rowData = $this->_em->cleanRow($data);
            $hydratedData = $this->_em->newHydrator($this->metadata, $hydrationMode)->hydrateRowData($rowData);

            return $hydratedData;
        }

        return;
    }

    public function getResult($hydrationMode = self::HYDRATE_OBJECT)
    {
        $result = $this->execute();
        $entities = [];
        foreach ($result as $data) {
            $rowData = $this->_em->cleanRow($data);
            $hydratedData = $this->_em->newHydrator($this->metadata, $hydrationMode)->hydrateRowData($rowData);

            $entities[] = $hydratedData;
        }

        return $entities;
    }

    public function getSingleScalarResult()
    {
        $result = $this->execute();
        if ($result && $data = $result->first()) {
            $rowData = $this->_em->cleanRow($data);

            return reset($rowData);
        }

        return 0;
    }
}
