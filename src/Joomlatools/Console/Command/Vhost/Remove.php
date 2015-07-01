<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Vhost;

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
            ->setDescription('Removes a Apache2 virtual host')
            ->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Alphanumeric site name, used in the site URL with .dev domain'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site = $input->getArgument('site');
        $file = '/etc/apache2/sites-available/1-' . $site . '.conf';

        if (is_file($file))
        {
            `sudo a2dissite 1-$site.conf`;
            `sudo rm $file`;
            `sudo /etc/init.d/apache2 restart > /dev/null 2>&1`;
        }
    }
}