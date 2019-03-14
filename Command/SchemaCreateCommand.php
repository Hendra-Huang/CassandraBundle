<?php

namespace CassandraBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $container = $this->getContainer();
        $schemaCreate = $container->get('cassandra.tools.schema_create');
        $schemaCreate->execute($input->getArgument('connection') ?: 'default');

        $output->writeln('Cassandra schema updated successfully!');
    }
}
