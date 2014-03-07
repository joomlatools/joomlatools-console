<?php

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteCreate extends SiteAbstract
{
    protected static $templates;

    protected $source_tar;
    protected $source_db;

    protected $symlink = array();
    protected $template;

    protected function configure()
    {
        parent::configure();

        if (!self::$templates) {
            self::$templates = '/home/vagrant/scripts/joomla_files';
        }

        $this
            ->setName('site:create')
            ->setDescription('Create a Joomla site')
            ->addOption(
                'template',
                null,
                InputOption::VALUE_OPTIONAL,
                'Template to build the site from (e.g. joomla25,joomla3)'
            )
            ->addOption(
                'symlink',
                null,
                InputOption::VALUE_OPTIONAL,
                'A comma separated list of folders to symlink from projects folder'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->template = $input->getOption('template');
        $this->symlink = $input->getOption('symlink');

        if (is_string($this->symlink)) {
            $this->symlink = explode(',', $this->symlink);
        }

        if ($this->template)
        {
            $this->source_tar = self::$templates.'/'.$this->template.'.tar.gz';
            $this->source_db  = self::$templates.'/'.$this->template.'.sql';
        }

        $this->check($input, $output);
        $this->createFolder($input, $output);
        $this->createDatabase($input, $output);
        $this->modifyConfiguration($input, $output);
        $this->addVirtualHost($input, $output);
        $this->symlinkProjects($input, $output);
        $this->installExtensions($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('A site with name %s already exists', $this->site));
        }

        if ($this->template)
        {
            if (!is_file($this->source_db)) {
                throw new \RuntimeException(sprintf('Database export is missing for template %s', $this->template));
            }

            if (!is_file($this->source_tar)) {
                throw new \RuntimeException(sprintf('Source files are missing for template %s', $this->template));
            }

            $result = `echo 'SHOW DATABASES LIKE "$this->target_db"' | mysql -uroot -proot`;
            if (!empty($result)) { // Table exists
                throw new \RuntimeException(sprintf('A database with name %s already exists', $this->target_db));
            }
        }
    }

    public function createFolder(InputInterface $input, OutputInterface $output)
    {
        `mkdir -p $this->target_dir`;

        if ($this->template) {
            `cd $this->target_dir; tar xzf $this->source_tar`;
        }
    }

    public function createDatabase(InputInterface $input, OutputInterface $output)
    {
        if (!$this->template) {
            return;
        }

        $result = `echo 'CREATE DATABASE $this->target_db CHARACTER SET utf8' | mysql -uroot -proot`;
        if (!empty($result)) { // MySQL returned an error
            throw new \RuntimeException(sprintf('Cannot create database %s. Error: %s', $this->target_db, $result));
        }

        $result = `mysql -proot -uroot $this->target_db < $this->source_db`;
        if (!empty($result)) { // MySQL returned an error
            throw new \RuntimeException(sprintf('Cannot import database. Error: %s', $result));
        }
    }

    public function modifyConfiguration(InputInterface $input, OutputInterface $output)
    {
        if (!$this->template) {
            return;
        }

        $file     = $this->target_dir.'/configuration.php';
        $contents = file_get_contents($file);
        $replace  = function($name, $value, &$contents) {
            $pattern     = sprintf("#%s = '.*?'#", $name);
            $replacement = sprintf("%s = '%s'", $name, $value);

            $contents = preg_replace($pattern, $replacement, $contents);
        };

        $replace('db', $this->target_db, $contents);
        $replace('tmp_path', sprintf('/var/www/%s/tmp',  $this->site), $contents);
        $replace('log_path', sprintf('/var/www/%s/logs', $this->site), $contents);
        $replace('sitename', $this->site, $contents);

        chmod($file, 0644);
        file_put_contents($file, $contents);
    }

    public function addVirtualHost(InputInterface $input, OutputInterface $output)
    {
        $template = file_get_contents(self::$templates.'/vhost.conf');
        $contents = sprintf($template, $this->site);

        $tmp = self::$templates.'/.vhost.tmp';

        file_put_contents($tmp, $contents);

        `sudo tee /etc/apache2/sites-available/1-$this->site.conf < $tmp`;
        `sudo a2ensite 1-$this->site.conf`;
        `sudo /etc/init.d/apache2 restart > /dev/null 2>&1`;

        @unlink($tmp);
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlink)
        {
            $symlink_input = new ArrayInput(array(
                'site:symlink',
                'site'    => $input->getArgument('site'),
                'symlink' => $this->symlink
            ));
            $symlink = new SiteSymlink();

            $symlink->run($symlink_input, $output);
        }
    }

    public function installExtensions(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlink)
        {
            $extension_input = new ArrayInput(array(
                'extension:install',
                'site'      => $input->getArgument('site'),
                'extension' => $this->symlink
            ));
            $installer = new ExtensionInstall();

            $installer->run($extension_input, $output);
        }
    }
}