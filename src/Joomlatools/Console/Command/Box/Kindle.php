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

class Kindle extends Site\AbstractSite
{
    protected function configure()
    {
        $this
            ->setName('box:kindle')
            ->setDescription('Bring up your burner box')
            ->addOption(
                'file_sync',
                'f',
                InputOption::VALUE_REQUIRED,
                "Select your type of file sync (nfs | docker-sync)",
                'nfs'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file_sync = $input->getOption('file_sync');

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
        elseif ($file_sync == 'nfs')
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

        sleep(5);

        $open_tabs = <<<EOT
        open -a "Google Chrome" http://localhost:8080 &&
        open -a "Google Chrome" http://localhost:8081 && 
        open -a "Google Chrome" http://localhost:8084 && 
        open -a "Google Chrome" http://localhost:3000
EOT;

        shell_exec($open_tabs);

        $output->writeln('<info>cleared for take off</info>');
    }
}