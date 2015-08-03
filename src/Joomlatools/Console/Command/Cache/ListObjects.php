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

class ListObjects extends AbstractCache
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
                'Specify the client cache to list',
                0
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        $items = $this->listCache($input, $output);
        $client_string = $input->getOption('client') == 1 ? 'administrative side' : 'front end';

        if(count($items))
        {
            foreach ($items as $item) {
                $output->writeln($item->group);
            }
        }
        else $output->writeln("<info>There appears to be no cache items for the $client_string</info>");
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function listCache(InputInterface $input, OutputInterface $output)
    {
        $client = $input->getOption('client');

        if (!$this->_isAPCEnabled())
        {
            $options = array(
                'cachebase' => $client ? JPATH_ADMINISTRATOR . '/cache' : JPATH_CACHE
            );

            $items = \JCache::getInstance('', $options)->getAll();
        }
        else
        {
            $items = $this->_doHTTP('list', $client);

            if ($items === false) {
                throw new \Exception('Could not query '.$this->url.'console-cache.php');
            }
        }

        return $items;
    }
}
