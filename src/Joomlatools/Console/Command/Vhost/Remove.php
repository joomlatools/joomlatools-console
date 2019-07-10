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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site    = $input->getArgument('site');
        $restart = [];
        $file    = '/etc/apache2/sites-available/1-' . $site . '.conf';

        if (is_file($file))
        {
            `sudo a2dissite 1-$site.conf`;
            `sudo rm $file`;

            $restart[] = 'apache';
        }

        $file = '/etc/nginx/sites-available/1-' . $site . '.conf';

        if (is_file($file))
        {
            `sudo rm -f $file`;
            `sudo rm -f /etc/nginx/sites-enabled/1-$site.conf`;

            $restart[] = 'nginx';
        }

        if (Util::isJoomlatoolsBox() && $restart)
        {
            $arguments = implode(' ', $restart);

            `box server:restart $arguments`;
        }
    }
}