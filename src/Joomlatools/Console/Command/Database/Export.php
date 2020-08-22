<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Util;

class Export extends AbstractDatabase
{
    protected function configure()
    {
        parent::configure();

        $this
        ->setName('database:exportt')
        ->setDescription('Export a database')
            ->addOption(
            'output-file',
            'o',
            InputOption::VALUE_REQUIRED,
            'The path to export the database to'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $output->writeln('<info>About to export database</info>');

        $output_dir = $input->getOption('output-file');
        $mysql_host = $input->getOption('mysql-host');
        $mysql_port = $input->getOption('mysql-port');

        $args = '-uroot --opt --skip-dump-date -B sites_alpha --column-statistics=0 sites_alpha ';
        $sed = 'sed \'s$VALUES ($VALUES\n($g\' | sed \'s$),($),\n($g\'';
        $file = $output_dir . $this->site . '.sql';

        shell_exec("MYSQL_PWD=root mysqldump -h $mysql_host -P $mysql_port $args > $file");

        $output->writeln("<info>File updated: " . $file . "</info>");

    }
}