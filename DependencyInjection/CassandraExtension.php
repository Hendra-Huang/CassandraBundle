<?php

namespace CassandraBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class CassandraExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $ormConfig = [];
        if (!empty($config['orm'])) {
            $ormConfig = $config['orm'];
        }
        $this->metadataFactoryLoad($container, $ormConfig);

        foreach ($config['connections'] as $connectionId => $connectionConfig) {
            $connectionConfig['dispatch_events'] = $config['dispatch_events'];
            $this->ormLoad($container, $connectionId, $connectionConfig);
        }
    }

    protected function metadataFactoryLoad(ContainerBuilder $container, array $config)
    {
        $classMetadataFactoryDefinition = $container
            ->register('cassandra.factory.metadata', 'CassandraBundle\\Cassandra\\ORM\\Mapping\\ClassMetadataFactory')
            ->addArgument(isset($config['mappings']) ? $config['mappings'] : [])
            ->addArgument(new Reference('annotation_reader'))
            ->setPublic(false);

        if (isset($config['metadata_cache_driver'])) {
            $cacheDriverReference = new Reference($config['metadata_cache_driver']);
            $classMetadataFactoryDefinition->addMethodCall('setCacheDriver', [$cacheDriverReference]);
        }
    }

    protected function ormLoad(ContainerBuilder $container, $connectionId, array $config)
    {
        $class = 'CassandraBundle\\Cassandra\\Connection';
        $definition = new Definition($class);
        $definition->addArgument($config);
        $definition->setConfigurator(['CassandraBundle\Cassandra\Configurator', 'buildCluster']);

        if ($config['dispatch_events']) {
            $definition->addMethodCall('setEventDispatcher', [new Reference('event_dispatcher')]);
        }
        $definition->setPublic(true);

        $container->setDefinition(sprintf('cassandra.connection.%s', $connectionId), $definition);

        $container
            ->register(sprintf('cassandra.%s_entity_manager', $connectionId), 'CassandraBundle\\Cassandra\\ORM\\EntityManager')
            ->addArgument(new Reference(sprintf('cassandra.connection.%s', $connectionId)))
            ->addArgument(new Reference('cassandra.factory.metadata'))
            ->addArgument(new Reference('logger'))
            ->setPublic(true);
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return 'cassandra';
    }
}
