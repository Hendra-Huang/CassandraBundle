<?php

namespace CassandraBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SchemaCreateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('cassandra:schema:create')
            ->setDescription('Drop and create cassandra table')
            ->addArgument(
                'connection',
                InputArgument::OPTIONAL,
                'Connection of cassandra'
            )
            ->addOption(
                'dump-cql',
                null,
                InputOption::VALUE_NONE,
                'Dumps the generated CQL statements to the screen (does not execute them).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $container = $this->getContainer();
        $schemaCreate = $container->get('cassandra.tools.schema_create');
        $connection = $input->getArgument('connection') ?: 'default';
        $dumpCql = true === $input->getOption('dump-cql');
        $dumpOutputs = $schemaCreate->execute($connection, $dumpCql);

        if ($dumpCql) {
            $output->writeln(sprintf('CQL schema dump for connection %', $connection));
            $output->writeln('#######');
            $output->writeln(implode(PHP_EOL, $dumpOutputs));
        } else {
            $output->writeln('Cassandra schema updated successfully!');
        }
    }
}
