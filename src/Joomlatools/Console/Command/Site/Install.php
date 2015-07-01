<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command;
use Joomlatools\Console\Command\Database;
use Joomlatools\Console\Command\Vhost;

class Install extends Database\AbstractDatabase
{
    /**
     * Projects to symlink
     *
     * @var array
     */
    protected $symlink = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:install')
            ->setDescription('Install an existing Joomla codebase. Sets up configuration and installs the database.')
            ->addOption(
                'sample-data',
                null,
                InputOption::VALUE_REQUIRED,
                'Sample data to install (default|blog|brochure|learn|testing)'
            )
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'Overwrite configuration.php if it already exists'
            )
            ->addOption(
                'drop',
                'd',
                InputOption::VALUE_NONE,
                'Drop database if it already exists'
            )
            ->addOption(
                'symlink',
                null,
                InputOption::VALUE_REQUIRED,
                'A comma separated list of folders to symlink from projects folder'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->symlink = $input->getOption('symlink');
        if (is_string($this->symlink)) {
            $this->symlink = explode(',', $this->symlink);
        }

        $this->check($input, $output);

        $this->importdb($input, $output);
        $this->createConfig($input, $output);

        if ($this->symlink)
        {
            $this->symlinkProjects($input, $output);
            $this->installExtensions($input, $output);
        }

        $this->enableWebInstaller($input, $output);

        $output->writeln("Your new Joomla site has been configured.");
        $output->writeln("You can login using the following username and password combination: <info>admin</info>/<info>admin</info>.");
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('The site %s does not exist!', $this->site));
        }
    }

    public function importdb(InputInterface $input, OutputInterface $output)
    {
        $arguments = array(
            'site:database:install',
            'site'          => $this->site
        );

        $sample_data = $input->getOption('sample-data');
        if (!empty($sample_data)) {
            $arguments['--sample-data'] = $sample_data;
        }

        if ($input->getOption('drop')) {
            $arguments['--drop'] = true;
        }

        $command = new Database\Install();
        $command->run(new ArrayInput($arguments), $output);
    }

    public function createConfig(InputInterface $input, OutputInterface $output)
    {
        $arguments = array(
            'site:configure',
            'site'          => $this->site
        );

        if ($input->getOption('overwrite')) {
            $arguments['--overwrite'] = true;
        }

        $command = new Configure();
        $command->run(new ArrayInput($arguments), $output);
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        $symlink_input = new ArrayInput(array(
            'site:symlink',
            'site'    => $input->getArgument('site'),
            'symlink' => $this->symlink,
            '--www'   => $this->www,
            '--projects-dir' => $input->getOption('projects-dir')
        ));
        $symlink = new Command\ExtensionSymlink();

        $symlink->run($symlink_input, $output);
    }

    public function installExtensions(InputInterface $input, OutputInterface $output)
    {
        $extension_input = new ArrayInput(array(
            'extension:install',
            'site'      => $input->getArgument('site'),
            'extension' => $this->symlink,
            '--www'     => $this->www
        ));
        $installer = new Command\ExtensionInstall();

        $installer->run($extension_input, $output);
    }

    public function enableWebInstaller(InputInterface $input, OutputInterface $output)
    {
        $version = $this->_getJoomlaVersion();

        if (version_compare($version, '3.2.0', '<')) {
            return;
        }

        $xml = simplexml_load_file('http://appscdn.joomla.org/webapps/jedapps/webinstaller.xml');

        if(!$xml)
        {
            $output->writeln('<warning>Failed to install web installer</warning>');

            return;
        }

        $url = '';
        foreach($xml->update->downloads->children() as $download)
        {
            $attributes = $download->attributes();
            if($attributes->type == 'full' && $attributes->format == 'zip')
            {
                $url = (string) $download;
                break;
            }
        }

        if(empty($url)) {
            return;
        }

        $filename = self::$files.'/cache/'.basename($url);
        if(!file_exists($filename))
        {
            $bytes = file_put_contents($filename, fopen($url, 'r'));
            if($bytes === false || $bytes == 0) {
                return;
            }
        }

        `mkdir -p $this->target_dir/plugins/installer`;
        `cd $this->target_dir/plugins/installer/ && unzip -o $filename`;

        $sql = "INSERT INTO `j_extensions` (`name`, `type`, `element`, `folder`, `enabled`, `access`, `manifest_cache`) VALUES ('plg_installer_webinstaller', 'plugin', 'webinstaller', 'installer', 1, 1, '{\"name\":\"plg_installer_webinstaller\",\"type\":\"plugin\",\"version\":\"".$xml->update->version."\",\"description\":\"Web Installer\"}');";
        $sql = escapeshellarg($sql);

        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
        exec(sprintf("mysql -u'%s' %s %s -e %s", $this->mysql->user, $password, $this->target_db, $sql));
    }
}
