<?php

namespace Joomlatools\Console\Command\Box;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Question;
use Joomlatools\Console\Joomla\Util;
use Joomlatools\Console\Command\Site;

class Kindle extends Site\AbstractSite
{
    protected $containers = ['apache','web', 'php_fpm', 'composer', 'db', 'theia', 'mailhog', 'phpmyadmin'];

    protected function configure()
    {
        $this->config = Util::getConfig();

        $this
            ->setName('box:kindle')
            ->setDescription('Bring up your burner box')
            ->addOption(
                'file_sync',
                'f',
                InputOption::VALUE_REQUIRED,
                "Select your type of file sync (nfs | docker-sync)",
                'nfs'
            )
            ->addOption(
                'xdebug',
                'x',
                InputOption::VALUE_NONE
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file_sync = $input->getOption('file_sync');
        $xdebug = $input->getOption('xdebug');

        if ($xdebug){
            $file_sync = '';
        }

        shell_exec("sh checkout-joomlatools.sh");

        if ($file_sync == 'docker-sync')
        {
            //is this first time set up
            $docker_sync = shell_exec('gem list docker-sync -i');

            if (!$docker_sync)
            {
                passthru('gem install docker-sync');

                $sub_command = "'puts Gem.user_dir'";
                $command = "if which ruby >/dev/null && which gem >/dev/null; then \n";
                $command .= 'PATH="$(ruby -r rubygems -e ' . $sub_command . ')/bin:$PATH"' . "\n";
                $command .= "fi \n";

                $output->writeln('<info>Complete installation by appending these lines to your bash profile</info>');
                $output->writeln($command);
            }

            passthru('docker-sync-stack start');

        }

        if ($file_sync == 'nfs')
        {
            if (!file_exists('.nfs_file_sharing'))
            {
                $output->writeLn('<info>About to set up mac nfs file sharing</info>');

                passthru('chmod +x docker_file_sharing.sh');

                passthru('sh docker_file_sharing.sh');

                shell_exec('touch .nfs_file_sharing');
            }

            passthru('docker-compose up -d');

        }

        if ($xdebug) {
            passthru('docker-compose -f docker-compose-xdebug.yml up -d');
        }

        sleep(5);

        $open_tabs = <<<EOT
        open -a "Google Chrome" http://localhost:8080 &&
        open -a "Google Chrome" http://localhost:8081 && 
        open -a "Google Chrome" http://localhost:8084 && 
        open -a "Google Chrome" http://localhost:3000
EOT;

        shell_exec($open_tabs);

        $finish_message = <<<EOT
                  ____
                (xXXXX|xx======---(-  IT'S A TRAP!!!!
                /     |
               /    XX|
              /xxx XXX|
             /xxx X   |
            / ________|
    __ ____/_|_|_______\_      Rogue One standing by.
###|=||________|_________|    /
    ~~   |==| __  _  __  /|~~~~~~~~~-------------_______
         |==| ||(( ||()|| |XXXXXXXX| powell@coventry.ac.uk
    __   |==| ~~__~__~~__\|_________-------------~~~~~~~
###|=||~~~~~~~~|_______  |
    ~~ ~~~~\~|~|       /~
            \ ~~~~~~~~~
             \xxx X   |
              \xxx XXX|
               \    XX|
                \     |
                (xXXXX|xx======---(-  Do or do not, there is no try!
                  ~~~~
EOT;


        $finish_message .= "\n\n May your code light the way \n";
        $output->writeln("<info>$finish_message</info>");
    }
}