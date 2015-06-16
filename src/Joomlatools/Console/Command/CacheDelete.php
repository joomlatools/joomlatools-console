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

        $group = $input->getOption('group');
        $client = $input->getOption('client');

        $options = array(
            'cachebase' => $client ? JPATH_ADMINISTRATOR . '/cache' : $config->get('cache_path', JPATH_SITE . '/cache')
        );

        $cache = \JCache::getInstance('', $options);
        $items = $cache->getAll();

        if($group){
            $cache->clean($group);
        }
        else
        {
            foreach($items as $item){
                $cache->clean($item->group);
            }
        }

        $client_string = $client ? 'administrative ' : 'front end ';
        $group_string = strlen($group) ? $group . ' cache items' : 'cache items';
        $output->writeln('<info>' . $client_string . $group_string . ' have been deleted');
    }
}
