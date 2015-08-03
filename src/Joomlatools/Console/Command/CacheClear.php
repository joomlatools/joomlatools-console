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

class CacheClear extends SiteAbstract
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('cache:clear')
            ->setDescription('Clear the Joomla cache')
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Specify a cache group to delete'
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

        $this->check($input, $output);
        $this->deleteCache($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function deleteCache(InputInterface $input, OutputInterface $output)
    {
        Bootstrapper::getApplication($this->target_dir);

        $group = $input->getOption('group');
        $client = $input->getOption('client');
        $client_string = $client ? 'administrative ' : 'front end ';

        $options = array(
            'cachebase' => $client ? JPATH_ADMINISTRATOR . '/cache' : JPATH_CACHE
        );

        $cache = \JCache::getInstance('', $options);

        if(!count($group)){
            $group = $cache->getAll();
        }

        if($group === false)
        {
            $output->writeln("<info>It appears that your cache is not enabled via the configuration</info>");
            return;
        }
        elseif(!count($group))
        {
            $output->writeln("<info>There appears to be no cache items for the $client_string</info>");
            return;
        }
        else
        {
            foreach($group as $item)
            {
                $cache_item = isset($item->group) ? $item->group : $item;
                $result = $cache->clean($cache_item);

                if($result){
                    $output->writeln('<info>' . $client_string . $cache_item . ' cache items have been deleted</info>');
                }
            }
        }
    }
}
