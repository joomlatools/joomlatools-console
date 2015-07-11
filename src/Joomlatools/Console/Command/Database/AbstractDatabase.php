<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;

abstract class AbstractDatabase extends AbstractSite
{
    protected $target_db;
    protected $target_db_prefix = 'sites_';

    protected $mysql;

    protected function configure()
    {
        parent::configure();

        $this->addOption(
            'mysql-login',
            'L',
            InputOption::VALUE_REQUIRED,
            "MySQL credentials in the form of user:password",
            'root:root'
        )
        ->addOption(
            'mysql-host',
            'H',
            InputOption::VALUE_REQUIRED,
            "MySQL host",
            'localhost'
        )
        ->addOption(
            'mysql_db_prefix',
            null,
            InputOption::VALUE_OPTIONAL,
            "MySQL database prefix (default: sites_)",
            $this->target_db_prefix
        )
        ->addOption(
            'mysql-database',
            'db',
            InputOption::VALUE_REQUIRED,
            "MySQL database name. If set, the --mysql_db_prefix option will be ignored."
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $db_name = $input->getOption('mysql-database');
        if (empty($db_name))
        {
            $this->target_db_prefix = $input->getOption('mysql_db_prefix');
            $this->target_db        = $this->target_db_prefix.$this->site;
        }
        else
        {
            $this->target_db_prefix = '';
            $this->target_db        = $db_name;
        }

        $credentials = explode(':', $input->getOption('mysql-login'), 2);

        $this->mysql = (object) array(
            'user'     => $credentials[0],
            'password' => $credentials[1],
            'host'     => $input->getOption('mysql-host')
        );
    }

    protected function _executeSQL($query)
    {
        $password = empty($this->mysql->password) ? '' : sprintf("--password='%s'", $this->mysql->password);
        $cmd      = sprintf("echo '$query' | mysql --host=%s --port=%s --user='%s' %s", $this->mysql->host, $this->mysql->port, $this->mysql->user, $password);

        return exec($cmd);
    }
}
