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
    protected $username = '';

    protected $password = '';

    protected $server = '';

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

        $this->user = $input->getArgument('user');
        $this->password = $input->getArgument('password');
        $this->server = $input->getArgument('server');

        $this->checkGit($input, $output);
        $this->checkGitFTP($input, $output);
        $this->deploy($input, $output);
    }

    public function checkGit(InputInterface $input, OutputInterface $output)
    {
        `cd $this->target_dir`;

        if(!file_exists($this->target_dir . '/.git'))
        {
            $result = exec('git init');
            $output->writeln($result);

            `touch .gitignore`;
            `echo ".git-ftp" > .gitignore`;

            $output->writeln('<info>New git repository added please make your first commit</info>');
            exit();
        }
    }

    public function checkGitFTP(InputInterface $input, OutputInterface $output)
    {
        if(!file_exists($this->target_dir . '/.git-ftp'))
        {
            $result = exec('git ftp init --user ' . $this->user . ' --passwd ' . $this->password . ' ' . $this->server);
            $output->writeln($result);

            `touch .git-ftp`;
            `echo "used for local deployment purposes, do not delete" > .git-ftp`;

            exit();
        }
    }

    public function deploy(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<info>about to deploy</info>");

        $result = exec('git ftp push --user ' . $this->user . ' --passwd ' . $this->password . ' ' .$this->server);
        $output->writeln("$result");
    }
}