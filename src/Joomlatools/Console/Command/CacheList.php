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

class CacheList extends SiteAbstract
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('cache:list')
            ->setDescription('List all caches for a client')
            ->addOption(
                'client',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Specify the client cache to delete',
                0
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        Bootstrapper::getApplication($this->target_dir);

        $config = \JFactory::getConfig();

        $client = $input->getOption('client');

        $options = array(
            'cachebase' => $client ? JPATH_ADMINISTRATOR . '/cache' : $config->get('cache_path', JPATH_SITE . '/cache')
        );

        $cache = \JCache::getInstance('', $options);
        $items = $cache->getAll();

        foreach($items as $item){
            $output->writeln($item->group);
        }
    }
}
