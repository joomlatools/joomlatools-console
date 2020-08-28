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
        ->setName('database:export')
        ->setDescription('Export a database')
            ->addOption(
            'output-file',
            'o',
            InputOption::VALUE_REQUIRED,
            'The path to export the database to',
            $this->config['mysqld_output']
<<<<<<< Updated upstream
=======
        )
        ->addOption(
            'all-dbs',
            'all',
            InputOption::VALUE_REQUIRED,
            'Whether all dbs should be exported',
            false
>>>>>>> Stashed changes
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $output->writeln('<info>About to export database</info>');

        $output_dir = $input->getOption('output-file');
        $mysql_host = $input->getOption('mysql-host');
        $mysql_port = $input->getOption('mysql-port');
        $site = $input->getArgument('site');
<<<<<<< Updated upstream

        $args = "-uroot --opt --skip-dump-date -B sites_$site";
        $sed = 'sed \'s$VALUES ($VALUES\n($g\' | sed \'s$),($),\n($g\'';
        $file = $output_dir . $this->site . '.sql';

        echo "\n" . "docker exec joomlatools_mysql sh -c 'MYSQL_PWD=root mysqldump -h $mysql_host -P $mysql_port $args > $file' \n";
=======
        $all_dbs = $input->getOption('all-dbs');


        $file = $output_dir . $this->site . '.sql';

        $dbs = '-B sites_' . $site;

        if ($all_dbs)
        {
            $dbs = '-A';
            $file = $output_dir . 'all-dbs.sql';
        }

        $args = "-uroot --opt --skip-dump-date $dbs";
        $sed = 'sed \'s$VALUES ($VALUES\n($g\' | sed \'s$),($),\n($g\'';

        #echo "\n" . "docker exec joomlatools_mysql sh -c 'MYSQL_PWD=root mysqldump -h $mysql_host -P $mysql_port $args > $file' \n";
>>>>>>> Stashed changes

        shell_exec("docker exec joomlatools_mysql sh -c 'MYSQL_PWD=root mysqldump -h $mysql_host -P $mysql_port $args > $file'");

        $output->writeln("<info>File updated: " . $file . "</info>");

    }
}