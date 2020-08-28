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

use Joomlatools\Console\Command\Database;

class Burn extends Site\AbstractSite
{
    protected function configure()
    {
        $this
            ->setName('box:burn')
            ->setDescription('Finished with your master piece, then burn the box... all dbs will be exported');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command_input = new ArrayInput(array(
            'db:export',
            'site'          => $this->site,
            '--all-dbs' => true
        ));

        $command = new Database\Export();
        $command->run($command_input, $output);

        $output->writeln('<info>About to destroy the box</info>');

        passthru('docker-compose down');

        passthru('docker container prune');
    }
}