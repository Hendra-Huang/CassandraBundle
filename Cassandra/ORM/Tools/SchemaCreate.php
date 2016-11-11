<?php

namespace CassandraBundle\Cassandra\ORM\Tools;

use Symfony\Component\DependencyInjection\ContainerInterface;

class SchemaCreate
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function execute($connection = 'default')
    {
        $em = $this->container->get(sprintf('cassandra.%s_entity_manager', $connection));
        $schemaManager = $em->getSchemaManager();

        // Get all files in src/*/Entity directories
        $path = $this->container->getParameter('kernel.root_dir').'/../src';
        $iterator = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            ),
            '/^.+'.preg_quote('.php').'$/i',
            \RecursiveRegexIterator::GET_MATCH
        );
        foreach ($iterator as $file) {
            $sourceFile = $file[0];
            if (!preg_match('(^phar:)i', $sourceFile)) {
                $sourceFile = realpath($sourceFile);
            }
            if (preg_match('/src\/.*Entity\//', $sourceFile)) {
                $className = str_replace('/', '\\', preg_replace('/(.*src\/)(.*).php/', '$2', $sourceFile));
                $metadata = $em->getClassMetadata($className);
                $tableName = $metadata->table['name'];
                $indexes = $metadata->table['indexes'];
                $primaryKeys = $metadata->table['primaryKeys'];

                if ($tableName) {
                    $schemaManager->dropTable($tableName);
                    $schemaManager->createTable($tableName, $metadata->fieldMappings, $primaryKeys);
                    $schemaManager->createIndexes($tableName, $indexes);
                }
            }
        }

        $em->closeAsync();
    }
}
