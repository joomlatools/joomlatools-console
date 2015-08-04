<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Deploy extends AbstractSite
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

        $this->user     = $input->getArgument('user');
        $this->password = $input->getArgument('password');
        $this->server   = $input->getArgument('server');

        chdir($this->target_dir);

        $this->checkGit($input, $output);
        $this->checkGitFTP($input, $output);
        $this->deploy($input, $output);
    }

    public function checkGit(InputInterface $input, OutputInterface $output)
    {
        if(!file_exists($this->target_dir . '/.git'))
        {
            $result = exec('git init');
            $output->writeln($result);

            `touch .gitignore`;
            `echo ".git-ftp" > .gitignore`;

            `git add -A`;
            `git commit -a -m "Initial commit"`;
        }
    }

    public function checkGitFTP(InputInterface $input, OutputInterface $output)
    {
        if(!file_exists($this->target_dir . '/.git-ftp'))
        {
            passthru('git ftp init --user ' . $this->user . ' --passwd ' . $this->password . ' ' . $this->server);

            `touch .git-ftp`;
            `echo "used for local deployment purposes, do not delete" > .git-ftp`;
        }
    }

    public function deploy(InputInterface $input, OutputInterface $output)
    {
        passthru('git ftp push --user ' . $this->user . ' --passwd ' . $this->password . ' ' .$this->server);
    }
}