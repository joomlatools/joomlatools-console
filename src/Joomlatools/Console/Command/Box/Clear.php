<?php

namespace Joomlatools\Console\Command\Box;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question;
use Joomlatools\Console\Joomla\Util;
use Joomlatools\Console\Command\Site;

class Clear extends Site\AbstractSite
{
    protected function configure()
    {
        $this
            ->setName('box:clear')
            ->setDescription('Clear logs, vhosts, db')
            ->addOption(
                'root',
                null,
                InputOption::VALUE_REQUIRED,
                "burner box root",
                $this->config['www_dir']
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = $input->getOption('root');

        shell_exec("rm -Rf $root/apache2/logs/*");

        //remove the apache2/vhosts
        shell_exec("find $root/apache2/vhosts -type f ! -name '2-default.conf' -delete");

        //remove the nginx/logs
        shell_exec("rm -Rf $root/nginx/logs/*");

        //remove the nginx/vhosts
        shell_exec("find $root/nginx/vhost -type f ! -name '2-default.conf' -delete");

        //remove any back up databases
        shell_exec("bash -c 'cat /dev/null > $root/mysql/all-dbs.sql'");

    }
}