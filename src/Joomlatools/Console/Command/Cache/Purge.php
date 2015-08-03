<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Cache;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Purge extends AbstractCache
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('cache:purge')
            ->setDescription('Purge all expired cache files')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->purgeCache($input, $output);
    }

    public function purgeCache(InputInterface $input, OutputInterface $output)
    {
        if ($this->_isAPCEnabled()) {
            $result = $this->_doHTTP('purge');
        }
        else $result = \JFactory::getCache()->gc();

        if ($result === false) {
            $output->writeln('<error>Error purging cached items</error>');
        }
        else $output->writeln('<info>All expired cache items have been deleted</info>');
    }
}
