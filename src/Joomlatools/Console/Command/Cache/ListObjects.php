<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Cache;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Cache;

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
                InputOption::VALUE_REQUIRED,
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

    public function listCache(InputInterface $input, OutputInterface $output)
    {
        $client = $input->getOption('client');

        if ($this->_isAPCEnabled())
        {
            $items = $this->_doHTTP('list', $client);

            if ($items === false) {
                throw new \Exception('Could not query '.$this->url.'console-cache.php');
            }
        }
        else $items = Cache::getGroups($client);

        return $items;
    }
}
