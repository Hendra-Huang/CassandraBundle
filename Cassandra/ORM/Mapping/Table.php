<?php

namespace CassandraBundle\Cassandra\ORM\Mapping;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class Table extends Annotation
{
    /**
     * @var string
     */
    public $repositoryClass;

    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $indexes = [];

    /**
     * @var array
     */
    public $primaryKeys = ['id'];

    /**
     * @var int
     */
    public $defaultTtl = null;

    /**
     * @var bool
     */
    public $ifNoExist = null;

    /**
     * @var array
     */
    public $tableOptions = [
        'compactStorage' => false,
        'clusteringOrder' => null,
    ];
}
