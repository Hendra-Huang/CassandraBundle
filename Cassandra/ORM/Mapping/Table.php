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
        'comment' => null,
        'speculative_retry' => null,
        'additional_write_policy' => null,
        'gc_grace_seconds' => null,
        'bloom_filter_fp_chance' => null,
        'default_time_to_live' => null,
        'compaction' => null,
        'compression' => null,
        'caching' => null,
        'memtable_flush_period_in_ms' => null,
        'read_repair' => null,
    ];
}
