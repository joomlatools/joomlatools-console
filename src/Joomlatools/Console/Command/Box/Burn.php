<?php

namespace Joomlatools\Console\Command\Box;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question;
use Joomlatools\Console\Joomla\Util;
use Joomlatools\Console\Command\Site;

use Joomlatools\Console\Command\Box;
use Joomlatools\Console\Command\Database;

class Burn extends Site\AbstractSite
{
    protected function configure()
    {
        $this->config = Util::getConfig();

        $this
            ->setName('box:burn')
            ->setDescription('Finished with your master piece, then burn the box... all dbs will be exported')
            ->addOption(
                'root',
                null,
                InputOption::VALUE_REQUIRED,
                $this->config['www_dir']
            )
            ->addOption(
                'scorched-earth',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'xdebug',
                'x',
                InputOption::VALUE_NONE
            );
    }

    /*
     *
     * We need to stop and remove an image if xdebug was previously enabled
     * docker rmi joomlatools-kindle_php_fpm:latest
     *
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $burn_to_the_ground = $input->getOption('scorched-earth');
        $kill_xdebug = $input->getOption('xdebug');

        if ($burn_to_the_ground)
        {
            $command_input = new ArrayInput(array(
                '--root' => $this->config['www_dir']
            ));

            $command = new Box\Clear();
            $command->run($command_input, $output);
        }
        else
        {
            $command_input = new ArrayInput(array(
                'db:export',
                'site'          => $this->site,
                '--all-dbs' => true
            ));

            $command = new Database\Export();
            $command->run($command_input, $output);
        }

        $output->writeln('<info>About to destroy the box</info>');

        passthru('docker-compose down');

        passthru('docker container prune');

        if ($kill_xdebug){
            passthru("docker rmi joomlatools-kindle_php_fpm:latest");
        }
    }
}