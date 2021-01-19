<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Vhost;

use Joomlatools\Console\Joomla\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:remove')
            ->setDescription('Removes the Apache2 and/or Nginx virtual host')
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
            )->addOption('nginx-path',
                null,
                InputOption::VALUE_REQUIRED,
                'The Nginx path',
                '/etc/nginx'
            )->addOption('apache-restart',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Apache2',
                null
            )->addOption('nginx-restart',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Nginx',
                null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site    = $input->getArgument('site');
        $restart = [];

        $file = sprintf('%s/available-sites/1-%s.conf', $input->getOption('apache-path'), $site);

        if (is_file($file))
        {
            $link = sprintf('%s/enabled-sites/1-%s.conf', $input->getOption('apache-path'), $site);

            if (is_file($link)) `sudo rm -f $link`;

            `sudo rm -f $file`;

            $restart[] = 'apache';
        }

        $file = sprintf('%s/available-sites/1-%s.conf', $input->getOption('nginx-path'), $site);

        if (is_file($file))
        {
            $link = sprintf('%s/enabled-sites/1-%s.conf', $input->getOption('nginx-path'), $site);

            if (is_file($link)) `sudo rm -f $link`;

            `sudo rm -f $file`;

            $restart[] = 'nginx';
        }

        if ($restart)
        {
            $ignored = array();

            foreach ($restart as $server)
            {
                if ($command = $input->getOption(sprintf('%s-restart', $server))) {
                    `sudo $command`;
                } else {
                    $ignored[] = $server;
                }
            }

            if (Util::isJoomlatoolsBox() && $ignored)
            {
                $arguments = implode(' ', $ignored);

                `box server:restart $arguments`;
            }
        }

        return 0;
    }
}