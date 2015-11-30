<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
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
                'server',
                InputArgument::REQUIRED,
                'FTP server to deploy to. You can add a different port or subdirectory. Example: ftp://ftp.domain.com:21/httpdocs'
            )
            ->addOption(
                'user',
                'U',
                InputOption::VALUE_REQUIRED,
                "FTP user",
                exec('whoami')
            )
            ->addOption(
                'password',
                'P',
                InputOption::VALUE_OPTIONAL,
                "FTP password. Omit for interactive password prompt."
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->server   = $input->getArgument('server');
        $this->user     = $input->getOption('user');
        $this->password = $input->getOption('password');

        $this->check($input, $output);

        chdir($this->target_dir);

        $this->checkGit($input, $output);

        $initialised = $this->initGitFTP($input, $output);

        if (!$initialised) {
            $this->deploy();
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site %s does not exist', $this->site));
        }

        $result = shell_exec('command -v git-ftp >/dev/null 2>&1 || { echo "false"; }');

        if (trim($result) == 'false')
        {
            $output->writeln('<error>ERROR:</error> git-ftp is not installed.');
            $output->writeln('Refer to https://github.com/git-ftp/git-ftp/blob/develop/INSTALL.md for installation instructions.');

            exit(1);
        }
    }

    public function checkGit()
    {
        if(!file_exists($this->target_dir . '/.git'))
        {
            passthru('git init');

            `touch .gitignore`;
            `echo ".git-ftp*" > .gitignore`;

            `git add -A`;
            `git commit -a -m "Initial commit"`;
        }
    }

    public function initGitFTP()
    {
        if(!file_exists($this->target_dir . '/.git-ftp'))
        {
            $password = $this->_buildPasswordString();

            passthru('git ftp init --user ' . $this->user . ' ' . $password . ' ' . $this->server);

            `touch .git-ftp`;
            `echo "used for local deployment purposes, do not delete" > .git-ftp`;

            return true;
        }

        return false;
    }

    public function deploy()
    {
        $password = $this->_buildPasswordString();

        passthru('git ftp push --user ' . $this->user . ' ' . $password . ' ' .$this->server);
    }

    protected function _getInstalledVersion()
    {
        $result = `git-ftp --version`;

        if (preg_match('/\d+(?:\.\d+)+/', $result, $matches)) {
            return $matches[0];
        }

        return '0.0.0';
    }

    protected function _buildPasswordString()
    {
        if (empty($this->password))
        {
            $version = $this->_getInstalledVersion();

            if (version_compare($version, '1.0.2', '<')) {
                return '-p -';
            }

            return '-P';
        }

        return '--passwd ' . $this->password;
    }
}