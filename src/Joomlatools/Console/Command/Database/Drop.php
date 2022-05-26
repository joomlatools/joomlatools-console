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

class Drop extends AbstractDatabase
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('database:drop')
            ->setDescription('Drop the site\'s database');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $result = $this->_executeSQL(sprintf("DROP DATABASE IF EXISTS `%s`", $this->target_db));

        if (!empty($result)) {
            throw new \RuntimeException(sprintf('Cannot drop database %s. Error: %s', $this->target_db, $result));
        }

        return 0;
    }
}