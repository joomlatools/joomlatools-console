<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class CacheDelete extends SiteAbstract
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('cache:delete')
            ->setDescription('Delete a Joomla site cache')
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_OPTIONAL,
                'Specify a cache group to delete',
                ''
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        Bootstrapper::getApplication($this->target_dir);

        $cache = \JFactory::getCache();
        $group = $input->getOption('group');

        $cache->clean($group);

        $cache_string = strlen($group) ? $group . ' cache items' : 'all front end cache items';
        $output->writeln('<info>' . $cache_string . ' have been deleted');
    }
}
