<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteDeploy extends SiteAbstract
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:deploy')
            ->setDescription('Deploy your website with git-ftp')
            ->addArgument(
                'user',
                InputArgument::REQUIRED,
                'What is your FTP username?'
            )
            ->addArgument(
                'password',
                InputArgument::REQUIRED,
                'What is your FTP password?'
            )
            ->addArgument(
                'server',
                InputArgument::REQUIRED,
                'What is your FTP server?'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        parent::execute($input, $output);

        $user = $input->getArgument('user');
        $password = $input->getArgument('password');
        $server = $input->getArgument('server');

        `cd $this->target_dir`;

        if(!file_exists($this->target_dir . '/.git'))
        {
            $result = exec('git init');
            $output->writeln($result);

            `touch .gitignore`;
            `echo ".git-ftp" > .gitignore`;

            `git add *`;

            $result = exec('git commit -m "inital commit"');
            $output->writeln($result);

            $output->writeln('<info>New git repository added, and initial commit created</info>');
        }

        if(!file_exists($this->target_dir . '/.git-ftp'))
        {
            $result = exec('git ftp init --user ' . $user . ' --passwd ' . $password . ' ' . $server);
            $output->write($result);

            if (strpos($result, "Last deployment") !== false)
            {
                `touch .git-ftp`;

                $contents = file_get_contents($this->target_dir . '/.gitignore');
                if(!strpos($contents, '.git-ftp'))
                {
                    $ignore = PHP_EOL . ".git-ftp" . PHP_EOL;
                    file_put_contents($this->target_dir . '/.gitignore', $ignore, FILE_APPEND);

                    `git add * `;

                    `git commit -m "amending gitignore to not version control .git-ftp"`;
                }
            }

            return;
        }

        $result = exec('git ftp push --user ' . $user . ' --passwd ' . $password . ' ' .$server);
        $output->writeln($result);
    }
}