<?php

namespace CassandraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * http://symfony.com/fr/doc/current/components/config/definition.html
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('cassandra');

        $rootNode
            ->children()
                ->booleanNode('dispatch_events')->defaultValue(true)->end()
                ->arrayNode('connections')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('id', false)
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(function ($v) {
                                return (\array_key_exists('load_balancing', $v) && 'dc-aware-round-robin' === $v['load_balancing']) && !\array_key_exists('dc_options', $v);
                            })
                            ->thenInvalid('"dc-aware-round-robin" load balancing option require a "dc_options" entry in your configuration')
                        ->end()
                        ->children()
                            ->booleanNode('persistent_sessions')->defaultValue(true)->end()
                            ->scalarNode('keyspace')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('load_balancing')
                                ->defaultValue('round-robin')
                                ->validate()
                                    ->ifNotInArray(['round-robin', 'dc-aware-round-robin'])
                                    ->thenInvalid('Invalid load balancing value "%s"')
                                ->end()
                            ->end()
                            ->arrayNode('dc_options')
                                ->children()
                                    ->scalarNode('local_dc_name')->isRequired()->cannotBeEmpty()->end()
                                    ->integerNode('host_per_remote_dc')->isRequired()->min(0)->end()
                                    ->booleanNode('remote_dc_for_local_consistency')->isRequired()->end()
                                ->end()
                            ->end()
                            ->scalarNode('default_consistency')
                                ->defaultValue('one')
                                ->validate()
                                    ->ifNotInArray(['one', 'any', 'two', 'three', 'quorum', 'all', 'local_quorum', 'each_quorum', 'serial', 'local_serial', 'local_one'])
                                    ->thenInvalid('Invalid consistency value "%s"')
                                ->end()
                            ->end()
                            ->integerNode('default_pagesize')->deFaultValue(10000)->end()
                            ->arrayNode('hosts')->isRequired()->requiresAtLeastOneElement()->performNoDeepMerging()
                                ->prototype('scalar')->end()
                            ->end()
                            ->integerNode('port')->defaultValue(9042)->end()
                            ->integerNode('protocol_version')->defaultValue(1)->end()
                            ->booleanNode('token_aware_routing')->defaultValue(true)->end()
                            ->scalarNode('user')->isRequired()->defaultValue('')->end()
                            ->scalarNode('password')->isRequired()->defaultValue('')->end()
                            ->booleanNode('ssl')->defaultValue(false)->end()
                            ->integerNode('default_timeout')->min(0)->end()
                            ->arrayNode('timeout')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->integerNode('connect')->isRequired()->defaultValue(5)->min(0)->end()
                                    ->integerNode('request')->isRequired()->defaultValue(5)->min(0)->end()
                                ->end()
                            ->end()
                            ->arrayNode('retries')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->integerNode('sync_requests')->defaultValue(0)->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        $this->addOrmSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Return a ORM configuration.
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addOrmSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('orm')
                    ->children()
                        ->arrayNode('mappings')
                            ->requiresAtLeastOneElement()
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->treatNullLike([])
                                ->performNoDeepMerging()
                                ->children()
                                    ->scalarNode('prefix')->isRequired()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('metadata_cache_driver')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }
}
