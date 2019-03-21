<?php

namespace CassandraBundle\Cassandra\ORM;

interface EntityManagerInterface
{
    public function getConnection();

    /**
     * @return string
     */
    public function getTargetedEntityDirectories();
}
