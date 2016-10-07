<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command;
use Joomlatools\Console\Command\Database;
use Joomlatools\Console\Command\Vhost;
use Joomlatools\Console\Joomla\Util;

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
                'A comma separated list of directories to symlink from the projects directory. Use \'all\' to symlink every folder.'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            )
            ->addOption(
                'interactive',
                null,
                InputOption::VALUE_NONE,
                'Prompt for configuration details'
            )
            ->addOption(
                'skip-exists-check',
                'e',
                InputOption::VALUE_NONE,
                'Do not check if database already exists or not.'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);


        if ($input->getOption('interactive')) {
            $this->_promptDatabaseDetails($input, $output);
        }

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

        $this->_enableWebInstaller($input, $output);

        $name = Util::isPlatform($this->target_dir) ? 'Joomla Platform application' : 'Joomla site';
        $output->writeln("Your new $name has been configured.");
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
            'site'   => $this->site,
            '--www'  => $this->www
        );

        $optionalArgs = array('sample-data', 'drop', 'mysql-login', 'mysql_db_prefix', 'mysql-host', 'mysql-port', 'mysql-database');
        foreach ($optionalArgs as $optionalArg)
        {
            $value = $input->getOption($optionalArg);
            if (!empty($value)) {
                $arguments['--' . $optionalArg] = $value;
            }
        }

        if ($input->getOption('interactive')) {
            $arguments['--skip-exists-check'] = true;
        }

        $command = new Database\Install();
        $command->run(new ArrayInput($arguments), $output);
    }

    public function createConfig(InputInterface $input, OutputInterface $output)
    {
        $arguments = array(
            'site:configure',
            'site'   => $this->site,
            '--www'  => $this->www
        );

        $optionalArgs = array('overwrite', 'mysql-login', 'mysql_db_prefix', 'mysql-host', 'mysql-port', 'mysql-database', 'mysql-driver', 'interactive');
        foreach ($optionalArgs as $optionalArg)
        {
            $value = $input->getOption($optionalArg);
            if (!empty($value)) {
                $arguments['--' . $optionalArg] = $value;
            }
        }

        $command = new Configure();
        $command->setApplication($this->getApplication());
        $command->skipDatabasePrompt();
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
        $symlink = new Command\Extension\Symlink();

        $symlink->run($symlink_input, $output);
    }

    public function installExtensions(InputInterface $input, OutputInterface $output)
    {
        $extension_input = new ArrayInput(array(
            'extension:install',
            'site'      => $input->getArgument('site'),
            'extension' => 'all',
            '--www'     => $this->www
        ));
        $installer = new Command\Extension\Install();

        $installer->run($extension_input, $output);
    }

    protected function _enableWebInstaller(InputInterface $input, OutputInterface $output)
    {
        if (Util::isPlatform($this->target_dir)) {
            return;
        }

        $version = Util::getJoomlaVersion($this->target_dir);

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
        exec(sprintf("mysql --host=%s --port=%u -u'%s' %s %s -e %s", $this->mysql->host, $this->mysql->port, $this->mysql->user, $password, $this->target_db, $sql));
    }
}
