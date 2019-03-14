<?php

namespace CassandraBundle\Tests\Units\DependencyInjection;

use mageekguy\atoum\test;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use CassandraBundle\DependencyInjection\CassandraExtension as TestedClass;

class CassandraExtension extends test
{
    public function testDefaultConfig()
    {
        $container = $this->getContainerForConfiguation('default-config');
        $container->compile();

        $this
            ->boolean($container->has('cassandra.connection.client_test'))
                ->isTrue()
            ->array($arguments = $container->getDefinition('cassandra.connection.client_test')->getArgument(0))
                ->hasSize(15)
                ->hasKeys($this->getDefaultConfigKeys())
                ->notHasKeys(['default_timeout'])
            ->boolean($arguments['dispatch_events'])
                ->isTrue()
            ->boolean($arguments['persistent_sessions'])
                ->isTrue()
            ->string($arguments['keyspace'])
                ->isEqualTo('test')
            ->array($endpoints = $arguments['hosts'])
                ->hasSize(3)
                ->string($endpoints[0])
                    ->isEqualTo('127.0.0.1')
                ->string($endpoints[1])
                    ->isEqualTo('127.0.0.2')
                ->string($endpoints[2])
                    ->isEqualTo('127.0.0.3')
            ->string($arguments['load_balancing'])
                ->isEqualTo('round-robin')
            ->string($arguments['default_consistency'])
                ->isEqualTo('one')
            ->integer($arguments['default_pagesize'])
                ->isEqualTo(10000)
            ->integer($arguments['port'])
                ->isEqualTo(9042)
            ->boolean($arguments['token_aware_routing'])
                ->isTrue()
            ->boolean($arguments['ssl'])
                ->isFalse()
            ->array($timeouts = $arguments['timeout'])
                ->hasSize(2)
                ->hasKeys(['connect', 'request'])
                ->integer($timeouts['connect'])
                    ->isEqualTo(5)
                ->integer($timeouts['request'])
                    ->isEqualTo(5)
            ->array($retries = $arguments['retries'])
                ->hasSize(1)
                ->integer($retries['sync_requests'])
                    ->isEqualTo(0)
        ;
    }

    public function testOverrideConfig()
    {
        $container = $this->getContainerForConfiguation('override-config');
        $container->compile();

        $this
            ->boolean($container->has('cassandra.connection.client_test'))
                ->isTrue()
            ->array($arguments = $container->getDefinition('cassandra.connection.client_test')->getArgument(0))
                ->hasSize(17)
                ->hasKeys($this->getDefaultConfigKeys(['default_timeout', 'dc_options']))
            ->boolean($arguments['dispatch_events'])
                ->isFalse()
            ->boolean($arguments['persistent_sessions'])
                ->isFalse()
            ->string($arguments['keyspace'])
                ->isEqualTo('test')
            ->array($endpoints = $arguments['hosts'])
                ->hasSize(3)
                ->string($endpoints[0])
                    ->isEqualTo('127.0.0.1')
                ->string($endpoints[1])
                    ->isEqualTo('127.0.0.2')
                ->string($endpoints[2])
                    ->isEqualTo('127.0.0.3')
            ->string($arguments['load_balancing'])
                ->isEqualTo('dc-aware-round-robin')
            ->array($dcOptions = $arguments['dc_options'])
                ->hasSize(3)
                ->hasKeys(['local_dc_name', 'host_per_remote_dc', 'remote_dc_for_local_consistency'])
                ->string($dcOptions['local_dc_name'])
                    ->isEqualTo('testdc')
                ->integer($dcOptions['host_per_remote_dc'])
                    ->isEqualTo(3)
                ->boolean($dcOptions['remote_dc_for_local_consistency'])
                    ->isFalse()
            ->string($arguments['default_consistency'])
                ->isEqualTo('two')
            ->integer($arguments['default_pagesize'])
                ->isEqualTo(1000)
            ->integer($arguments['port'])
                ->isEqualTo(8906)
            ->boolean($arguments['token_aware_routing'])
                ->isFalse()
            ->boolean($arguments['ssl'])
                ->isTrue()
            ->integer($arguments['default_timeout'])
                ->isEqualTo(5)
            ->array($timeouts = $arguments['timeout'])
                ->hasSize(2)
                ->hasKeys(['connect', 'request'])
                ->integer($timeouts['connect'])
                    ->isEqualTo(15)
                ->integer($timeouts['request'])
                    ->isEqualTo(15)
            ->string($arguments['user'])
                ->isEqualTo('username')
            ->string($arguments['password'])
                ->isEqualTo('password')
            ->array($retries = $arguments['retries'])
                ->hasSize(1)
                ->integer($retries['sync_requests'])
                    ->isEqualTo(1)
        ;
    }

    public function testOverrideDefaultEndPointsConfig()
    {
        $container = $this->getContainerForConfiguation('override-config-with-import');
        $container->compile();

        $this
            ->boolean($container->has('cassandra.connection.client_test'))
                ->isTrue()
            ->array($arguments = $container->getDefinition('cassandra.connection.client_test')->getArgument(0))
            ->array($endpoints = $arguments['hosts'])
                ->hasSize(3)
                ->string($endpoints[0])
                    ->isEqualTo('127.0.0.4')
                ->string($endpoints[1])
                    ->isEqualTo('127.0.0.5')
                ->string($endpoints[2])
                    ->isEqualTo('127.0.0.6')
        ;
    }

    public function testMulticlientsConfig()
    {
        $container = $this->getContainerForConfiguation('multiclients');
        $container->compile();

        $this
            ->boolean($container->has('cassandra.connection.client_test'))
                ->isTrue()
            ->array($arguments = $container->getDefinition('cassandra.connection.client_test')->getArgument(0))
                ->hasSize(15)
                ->hasKeys($this->getDefaultConfigKeys())
            ->boolean($arguments['dispatch_events'])
                ->isTrue()
            ->string($arguments['keyspace'])
                ->isEqualTo('test')
            ->array($endpoints = $arguments['hosts'])
                ->hasSize(3)
                ->string($endpoints[0])
                    ->isEqualTo('127.0.0.1')
                ->string($endpoints[1])
                    ->isEqualTo('127.0.0.2')
                ->string($endpoints[2])
                    ->isEqualTo('127.0.0.3')
            ->boolean($container->has('cassandra.connection.client_test2'))
                ->isTrue()
            ->array($arguments2 = $container->getDefinition('cassandra.connection.client_test2')->getArgument(0))
                ->hasSize(16)
                ->hasKeys($this->getDefaultConfigKeys(['dc_options']))
            ->boolean($arguments['dispatch_events'])
                ->isTrue()
            ->string($arguments2['keyspace'])
                ->isEqualTo('test2')
            ->array($endpoints2 = $arguments2['hosts'])
                ->hasSize(2)
                ->string($endpoints2[0])
                    ->isEqualTo('127.0.0.4')
                ->string($endpoints2[1])
                    ->isEqualTo('127.0.0.5')
            ->string($arguments2['user'])
                ->isEqualTo('usertest')
            ->string($arguments2['password'])
                ->isEqualTo('passwdtest')
        ;
    }

    /**
     * @dataProvider unexpectedConfigValueDataProvider
     */
    public function testUnexpectedValueConfig($configs)
    {
        $parameterBag = new ParameterBag(['kernel.debug' => true]);
        $container = new ContainerBuilder($parameterBag);

        $this->if($extension = new TestedClass())
            ->exception(function () use ($extension, $configs, $container) {
                $extension->load($configs, $container);
            })
            ->isInstanceOf('\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
    }

    public function testInvalidConfig()
    {
        // no option for dc aware load balancing
        $configs = [[
            'connections' => [
                'client_test' => [
                    'keyspace' => 'test', 'hosts' => ['127.0.0.1'], 'load_balancing' => 'dc-aware-round-robin',
                ],
            ],
        ]];

        $parameterBag = new ParameterBag(['kernel.debug' => true]);
        $container = new ContainerBuilder($parameterBag);

        $this->if($extension = new TestedClass())
            ->exception(function () use ($extension, $configs, $container) {
                $extension->load($configs, $container);
            })
            ->isInstanceOf('\InvalidArgumentException');
    }

    public function testConfigurator()
    {
        $container = $this->getContainerForConfiguation('default-config');
        $container->compile();

        $this
            ->object($client = $container->get('cassandra.connection.client_test'))
                ->isInstanceOf('CassandraBundle\Cassandra\Connection')
            ->object($client->getCluster())
                ->isInstanceOf('Cassandra\DefaultCluster');
    }

    protected function unexpectedConfigValueDataProvider()
    {
        return [
            // bad load balancing
            [[[
                'connections' => [
                    'client_test' => [
                        'keyspace' => 'test', 'hosts' => ['127.0.0.1'], 'load_balancing' => 'invalid',
                    ],
                ],
            ]]],
            // bad consistency
            [[[
                'connections' => [
                    'client_test' => [
                        'keyspace' => 'test', 'hosts' => ['127.0.0.1'], 'default_consistency' => 'invalid',
                    ],
                ],
            ]]],
        ];
    }

    protected function getContainerForConfiguation($fixtureName)
    {
        $extension = new TestedClass();

        $parameterBag = new ParameterBag(['kernel.debug' => true]);
        $container = new ContainerBuilder($parameterBag);
        $container->set('event_dispatcher', new \mock\Symfony\Component\EventDispatcher\EventDispatcherInterface());
        $container->set('logger', new \mock\Psr\Log\LoggerInterface());
        $container->set('annotation_reader', new \mock\Doctrine\Common\Annotations\Reader());
        $container->set('cassandra.factory.metadata', new \mock\CassandraBundle\Cassandra\ORM\Mapping\ClassMetadataFactoryInterface());
        $container->registerExtension($extension);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../Fixtures/'));
        $loader->load($fixtureName.'.yml');

        return $container;
    }

    protected function getDefaultConfigKeys(array $keySup = [])
    {
        return array_merge(
                [
                'persistent_sessions',
                'keyspace',
                'hosts',
                'load_balancing',
                'default_consistency',
                'default_pagesize',
                'port',
                'token_aware_routing',
                'ssl',
                'timeout',
                'retries',
                'user',
                'password',
                ],
                $keySup
            );
    }
}
