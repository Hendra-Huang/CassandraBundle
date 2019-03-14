<?php

namespace CassandraBundle\Cassandra\ORM\Repository;

use CassandraBundle\Cassandra\ORM\EntityManagerInterface;

/**
 * Interface for entity repository factory.
 */
interface RepositoryFactory
{
    /**
     * Gets the repository for an entity class.
     *
     * @param \CassandraBundle\Cassandra\ORM\EntityManagerInterface $entityManager the EntityManager instance
     * @param string                                                $entityName    the name of the entity
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getRepository(EntityManagerInterface $entityManager, $entityName);
}
