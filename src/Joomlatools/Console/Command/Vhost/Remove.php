<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Vhost;

use Joomlatools\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Command\Configurable
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:remove')
            ->setDescription('Removes the Apache2 virtual host')
            ->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Alphanumeric site name, used in the site URL with .test domain'
            )
            ->addOption('apache-path',
                null,
                InputOption::VALUE_REQUIRED,
                'The Apache2 path',
                '/etc/apache2'
            )->addOption('apache-restart',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Apache2',
                null
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site    = $input->getArgument('site');

        $file = sprintf('%s/sites-available/100-%s.conf', $input->getOption('apache-path'), $site);

        if (is_file($file))
        {
            $link = sprintf('%s/sites-enabled/100-%s.conf', $input->getOption('apache-path'), $site);

            if (is_file($link)) $this->_runWithOrWithoutSudo("rm -f $link");

            $this->_runWithOrWithoutSudo("rm -f $file");

            if ($command = $input->getOption('apache-restart')) {
                $this->_runWithOrWithoutSudo($command);
            }
        }

        return 0;
    }

    protected function _runWithOrWithoutSudo($command) 
    {
        $hasSudo = `which sudo`;

        if ($hasSudo) {
            `sudo $command`;
        } else {
            `$command`;
        }
    }
}