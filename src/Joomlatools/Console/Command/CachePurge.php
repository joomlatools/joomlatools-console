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

class CachePurge extends SiteAbstract
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

        $this->check($input, $output);
        $this->purgeCache($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function purgeCache(InputInterface $input, OutputInterface $output)
    {
        Bootstrapper::getApplication($this->target_dir);

        $cache = \JCache::getInstance();
        $delete = $cache->gc();

        if($delete === false){
            $output->writeln('<error>Error purging cached items</error>');
        }else
            $output->writeln('<info>All expired cache items have been deleted</info>');
    }
}
