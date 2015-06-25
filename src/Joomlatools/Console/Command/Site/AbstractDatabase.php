<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractDatabase extends AbstractSite
{
    protected $target_db;
    protected $target_db_prefix = 'sites_';

    protected $mysql;

    protected function configure()
    {
        parent::configure();

        $this->addOption(
            'mysql',
            null,
            InputOption::VALUE_REQUIRED,
            "MySQL credentials in the form of user:password",
            'root:root'
        )
        ->addOption(
            'mysql_db_prefix',
            null,
            InputOption::VALUE_OPTIONAL,
            "MySQL database prefix (default: sites_)",
            $this->target_db_prefix
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->target_db_prefix = $input->getOption('mysql_db_prefix');
        $this->target_db        = $this->target_db_prefix.$this->site;

        $credentials = explode(':', $input->getOption('mysql'), 2);
        $this->mysql = (object) array('user' => $credentials[0], 'password' => $credentials[1]);
    }
}
