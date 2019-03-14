<?php

namespace CassandraBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class CassandraBundle.
 */
class CassandraBundle extends Bundle
{
    /**
     * @return DependencyInjection\CassandraExtension|\Symfony\Component\DependencyInjection\Extension\ExtensionInterface|null
     */
    public function getContainerExtension()
    {
        return new DependencyInjection\CassandraExtension();
    }
}
