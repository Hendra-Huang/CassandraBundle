<?php

namespace CassandraBundle\Cassandra\ORM\Tools;

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

        $entityDirectoriesRegexp = '/src\/.*Entity\//';
        $entityDirectories = $em->getTargetedEntityDirectories();
        if (!empty($entityDirectories)) {
            $entityDirectories = array_map(function ($entityDirectory) {
                return str_replace('/', '\/', $entityDirectory);
            }, $entityDirectories);
            $entityDirectoriesRegexp = sprintf('/((%s))/', implode(')|(', $entityDirectories));
        }

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
            if (preg_match($entityDirectoriesRegexp, $sourceFile)) {
                $className = str_replace('/', '\\', preg_replace('/(.*src\/)(.*).php/', '$2', $sourceFile));
                $metadata = $em->getClassMetadata($className);
                $tableName = $metadata->table['name'];
                $indexes = $metadata->table['indexes'];
                $primaryKeys = $metadata->table['primaryKeys'];
                $tableOptions = $metadata->table['tableOptions'];

                if ($tableName) {
                    $schemaManager->dropTable($tableName);
                    $schemaManager->createTable($tableName, $metadata->fieldMappings, $primaryKeys, $tableOptions);
                    $schemaManager->createIndexes($tableName, $indexes);
                }
            }
        }

        $em->closeAsync();
    }
}
