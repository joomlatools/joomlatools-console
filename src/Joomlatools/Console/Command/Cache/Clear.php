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

use Joomlatools\Console\Joomla\Cache;

class Clear extends AbstractCache
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

        $group   = $input->getOption('group');
        $client  = $input->getOption('client');
        $client_string = $client ? 'administrative ' : 'front end ';

        $deleted = $this->deleteCache($client, $group);
        foreach ($deleted as $item) {
            $output->writeln('<info>' . $client_string . $item . ' cache items have been deleted</info>');
        }

        if (!count($deleted)) {
            $output->writeln("<info>There are no $client_string cache items to delete</info>");
        }
    }

    public function deleteCache($client, $group = array())
    {
        if ($this->_isAPCEnabled())
        {
            $deleted = $this->_doHTTP('clear', $client, $group);

            if ($deleted === false) {
                throw new \Exception('Could not query '.$this->url.'console-cache.php');
            }
        }
        else $deleted = Cache::clear($client, $group);

        return $deleted;
    }
}
