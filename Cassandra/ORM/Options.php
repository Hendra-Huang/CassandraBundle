<?php

namespace CassandraBundle\Cassandra\ORM;

/**
 * Class Options.
 */
class Options
{
    /**
     * @var int
     */
    private $ttl;
    /**
     * @var bool
     */
    private $ifNoExist;

    /**
     * @return int
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * @param int $ttl
     *
     * @return Options
     */
    public function setTtl($ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIfNoExist()
    {
        return $this->ifNoExist;
    }

    /**
     * @param bool $ifNoExist
     *
     * @return Options
     */
    public function setIfNoExist($ifNoExist)
    {
        $this->ifNoExist = $ifNoExist;

        return $this;
    }
}
