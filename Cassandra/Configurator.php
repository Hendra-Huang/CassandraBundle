<?php

namespace CassandraBundle\Cassandra;

use Cassandra\Cluster\Builder;
use Cassandra\SSLOptions\Builder as SSLOptionsBuilder;

/**
 * Class Configurator.
 *
 * Configure cluster for cassandra connection
 */
class Configurator
{
    /**
     * Configure given connection.
     *
     * @param Connection $connection
     */
    public static function buildCluster(Connection $connection)
    {
        $config = $connection->getConfig();

        $consistency = \constant('\Cassandra::CONSISTENCY_'.strtoupper($config['default_consistency']));

        $cluster = new Builder();
        $cluster
            ->withDefaultConsistency($consistency)
            ->withDefaultPageSize($config['default_pagesize'])
            ->withContactPoints(implode(',', $config['hosts']))
            ->withPort($config['port'])
            ->withTokenAwareRouting($config['token_aware_routing'])
            ->withConnectTimeout($config['timeout']['connect'])
            ->withRequestTimeout($config['timeout']['request'])
            ->withPersistentSessions($config['persistent_sessions'])
            ->withCredentials($config['user'], $config['password'])
            ->withProtocolVersion($config['protocol_version']);

        if (isset($config['ssl']) && true === $config['ssl']) {
            $ssl = new SSLOptionsBuilder();
            $sslOption = $ssl->withVerifyFlags(\Cassandra::VERIFY_NONE)->build();
            $cluster->withSSL($sslOption);
        }

        if (\array_key_exists('default_timeout', $config)) {
            $cluster->withDefaultTimeout($config['default_timeout']);
        }

        if ('round-robin' == $config['load_balancing']) {
            $cluster->withRoundRobinLoadBalancingPolicy();
        } else {
            $dcOption = $config['dc_options'];
            $cluster->withDatacenterAwareRoundRobinLoadBalancingPolicy($dcOption['local_dc_name'], $dcOption['host_per_remote_dc'], $dcOption['remote_dc_for_local_consistency']);
        }

        $connection->setCluster($cluster->build());
    }
}
