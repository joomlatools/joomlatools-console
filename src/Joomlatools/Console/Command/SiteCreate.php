<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteCreate extends SiteAbstract
{
    /**
     * File cache
     *
     * @var string
     */
    protected static $files;

    /**
     * Downloaded Joomla tarball
     *
     * @var
     */
    protected $source_tarball;

    /**
     * Path to database export in Joomla tarball
     *
     * @var
     */
    protected $source_db;

    /**
     * Sample data to install
     *
     * @var string
     */
    protected $sample_data;

    /**
     * Clear cache before fetching versions
     * @var bool
     */
    protected $clear_cache = false;

    protected $template;

    /**
     * Joomla version to install
     *
     * @var string
     */
    protected $version;

    /**
     * Projects to symlink
     * @var array
     */
    protected $symlink = array();

    /**
     * @var Versions
     */
    protected $versions;

    /**
     *
     */

    protected function configure()
    {
        parent::configure();

        if (!self::$files) {
            self::$files = realpath(__DIR__.'/../../../../bin/.files');
        }

        $this
            ->setName('site:create')
            ->setDescription('Create a Joomla site')
            ->addOption(
                'joomla',
                null,
                InputOption::VALUE_REQUIRED,
                "Joomla version. Can be a release number (2, 3.2, ..) or branch name. Run `joomla versions` for a full list.\nUse \"none\" for an empty virtual host.",
                'latest'
            )
            ->addOption(
                'sample-data',
                null,
                InputOption::VALUE_REQUIRED,
                'Sample data to install (default|blog|brochure|learn|testing)'
            )
            ->addOption(
                'symlink',
                null,
                InputOption::VALUE_REQUIRED,
                'A comma separated list of folders to symlink from projects folder'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Update the list of available tags and branches from the Joomla repository'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            )
            ->addOption(
                'enable-ssl',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enable SSL for this site (Yes for enable it, No for not enable it)',
                'No'
            )
            ->addOption(
                'ssl-crt',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the signed cerfificate file',
                '/etc/apache2/ssl/server.crt'
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the private cerfificate file',
                '/etc/apache2/ssl/server.key'
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'The port on which the server will listen for SSL requests',
                '443'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->versions = new Versions();

        if ($input->getOption('clear-cache')) {
            $this->versions->refresh();
        }

        $this->setVersion($input->getOption('joomla'));

        $this->symlink = $input->getOption('symlink');
        if (is_string($this->symlink)) {
            $this->symlink = explode(',', $this->symlink);
        }

        $this->sample_data = $input->getOption('sample-data');

        $this->source_db = $this->target_dir.'/installation/sql/mysql/joomla.sql';

        $this->check($input, $output);
        $this->createFolder($input, $output);
        $this->createDatabase($input, $output);
        $this->modifyConfiguration($input, $output);
        $this->addVirtualHost($input, $output);
        $this->symlinkProjects($input, $output);
        $this->installExtensions($input, $output);
        $this->enableWebInstaller($input, $output);

        if ($this->version)
        {
            $output->writeln("Your new Joomla site has been created.");
            $output->writeln("You can login using the following username and password combination: <info>admin</info>/<info>admin</info>.");
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('A site with name %s already exists', $this->site));
        }

        if ($this->version)
        {
            $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
            $result = exec(sprintf(
                    "echo 'SHOW DATABASES LIKE \"%s\"' | mysql -u'%s' %s",
                    $this->target_db, $this->mysql->user, $password
                )
            );

            if (!empty($result)) { // Table exists
                throw new \RuntimeException(sprintf('A database with name %s already exists', $this->target_db));
            }

            $this->source_tarball = $this->getTarball($this->version, $output);
            if(!file_exists($this->source_tarball)) {
                throw new \RuntimeException(sprintf('File %s does not exist', $this->source_tarball));
            }
        }

        if ($this->version && $this->sample_data)
        {
            if (!in_array($this->sample_data, array('default', 'blog', 'brochure', 'testing', 'learn'))) {
                throw new \RuntimeException(sprintf('Unknown sample data "%s"', $this->sample_data));
            }

            if(is_numeric(substr($this->version, 0, 1)))
            {
                if (in_array($this->sample_data, array('testing', 'learn')) && version_compare($this->version, '3.0.0', '<')) {
                    throw new \RuntimeException(sprintf('%s does not support sample data %s', $this->version, $this->sample_data));
                }
            }
        }
    }

    public function createFolder(InputInterface $input, OutputInterface $output)
    {
        `mkdir -p $this->target_dir`;

        if ($this->version)
        {
            `cd $this->target_dir; tar xzf $this->source_tarball --strip 1`;

            if ($this->versions->isBranch($this->version)) {
                unlink($this->source_tarball);
            }
        }
    }

    public function createDatabase()
    {
        if (!$this->version) {
            return;
        }

        $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
        $result = exec(
            sprintf(
                "echo 'CREATE DATABASE `%s` CHARACTER SET utf8' | mysql -u'%s' %s",
                $this->target_db, $this->mysql->user, $password
            )
        );

        if (!empty($result)) { // MySQL returned an error
            throw new \RuntimeException(sprintf('Cannot create database %s. Error: %s', $this->target_db, $result));
        }

        $imports = array($this->target_dir.'/installation/sql/mysql/joomla.sql');

        $users = 'joomla3.users.sql';
        if(is_numeric(substr($this->version, 0, 1)) && version_compare($this->version, '3.0.0', '<')) {
            $users = 'joomla2.users.sql';
        }

        $imports[] = self::$files.'/'.$users;

        if ($this->sample_data)
        {
            $type = $this->sample_data == 'default' ? 'data' : $this->sample_data;
            $sample_db = $this->target_dir.'/installation/sql/mysql/sample_' . $type . '.sql';

            $imports[] = $sample_db;
        }

        foreach($imports as $import)
        {
            $contents = file_get_contents($import);
            $contents = str_replace('#__', 'j_', $contents);
            file_put_contents($import, $contents);

            $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
            $result = exec(sprintf("mysql -u'%s' %s %s < %s", $this->mysql->user, $password, $this->target_db, $import));

            if (!empty($result)) { // MySQL returned an error
                throw new \RuntimeException(sprintf('Cannot import database "%s". Error: %s', basename($import), $result));
            }
        }
    }

    public function modifyConfiguration()
    {
        if (!$this->version) {
            return;
        }

        $source   = $this->target_dir.'/installation/configuration.php-dist';
        $target   = $this->target_dir.'/configuration.php';

        $contents = file_get_contents($source);
        $replace  = function($name, $value, &$contents) {
            $pattern = sprintf("#%s = '.*?'#", $name);
            $match   = preg_match($pattern, $contents);

            if(!$match)
            {
                $pattern 	 = "/^\s?(\})\s?$/m";
                $replacement = sprintf("\tpublic \$%s = '%s';\n}", $name, $value);
            }
            else $replacement = sprintf("%s = '%s'", $name, $value);

            $contents = preg_replace($pattern, $replacement, $contents);
        };
        $remove   = function($name, &$contents) {
            $pattern  = sprintf("#public \$%s = '.*?'#", $name);
            $contents = preg_replace($pattern, '', $contents);
        };
        $random   = function($length) {
            $charset ='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $string  = '';
            $count   = strlen($charset);

            while ($length--) {
                $string .= $charset[mt_rand(0, $count-1)];
            }

            return $string;
        };

        $replacements = array(
            'db'        => $this->target_db,
            'user'      => $this->mysql->user,
            'password'  => $this->mysql->password,
            'dbprefix'  => 'j_',
            'dbtype'    => 'mysqli',

            'mailer' => 'smtp',
            'mailfrom' => 'admin@example.com',
            'fromname' => $this->site,
            'sendmail' => '/usr/bin/env catchmail',
            'smtpauth' => '0',
            'smtpuser' => '',
            'smtppass' => '',
            'smtphost' => 'localhost',
            'smtpsecure' => 'none',
            'smtpport' => '1025',

            'sef'       => '1',
            'sef_rewrite'   => '1',
            'unicodeslugs'  => '1',

            'debug'     => '1',
            'lifetime'  => '600',
            'tmp_path'  => $this->target_dir.'/tmp',
            'log_path'  => $this->target_dir.'/logs',
            'sitename'  => $this->site,

            'secret'    => $random(16)
        );

        foreach($replacements as $key => $value) {
            $replace($key, $value, $contents);
        }

        $remove('root_user', $contents);

        file_put_contents($target, $contents);
        chmod($target, 0644);

        `mv $this->target_dir/installation $this->target_dir/_installation`;
        `cp $this->target_dir/htaccess.txt $this->target_dir/.htaccess`;
    }

    public function addVirtualHost(InputInterface $input, OutputInterface $output)
    {
        if (is_dir('/etc/apache2/sites-available'))
        {
            $tmp = self::$files.'/.vhost.tmp';

            $template = file_get_contents(self::$files.'/vhost.conf');

            file_put_contents($tmp, sprintf($template, $this->site));

            if (strtolower($input->getOption('enable-ssl')) == 'yes')
            {
                $ssl_crt = $input->getOption('ssl-crt');
                $ssl_key = $input->getOption('ssl-key');
                $ssl_port = $input->getOption('ssl-port');

                if (file_exists($ssl_crt) && file_exists($ssl_key))
                {
                    $template = "\n\n" . file_get_contents(self::$files . '/vhost.ssl.conf');
                    file_put_contents($tmp, sprintf($template, $ssl_port, $this->site, $ssl_crt, $ssl_key), FILE_APPEND);
                }
            }

            `sudo tee /etc/apache2/sites-available/1-$this->site.conf < $tmp`;
            `sudo a2ensite 1-$this->site.conf`;
            `sudo /etc/init.d/apache2 restart > /dev/null 2>&1`;

            @unlink($tmp);
        }
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlink)
        {
            $symlink_input = new ArrayInput(array(
                'site:symlink',
                'site'    => $input->getArgument('site'),
                'symlink' => $this->symlink,
                '--www'   => $this->www,
                '--projects-dir' => $input->getOption('projects-dir')
            ));
            $symlink = new ExtensionSymlink();

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
                'extension' => $this->symlink,
                '--www'     => $this->www
            ));
            $installer = new ExtensionInstall();

            $installer->run($extension_input, $output);
        }
    }

    public function enableWebInstaller(InputInterface $input, OutputInterface $output)
    {
        if(!$this->version || version_compare($this->version, '3.2.0', '<')) {
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

    public function setVersion($version)
    {
        $result = $version;

        if (strtolower($version) === 'latest') {
            $result = $this->versions->getLatestRelease();
        }
        else
        {
            $length = strlen($version);
            $format = is_numeric($version) || preg_match('/^\d\.\d+$/im', $version);

            if ( ($length == 1 || $length == 3) && $format)
            {
                $result = $this->versions->getLatestRelease($version);

                if($result == '0.0.0') {
                    $result = $version.($length == 1 ? '.0.0' : '.0');
                }
            }
        }

        $this->version = $result;
    }

    public function getTarball($version, OutputInterface $output)
    {
        $tar   = $this->version.'.tar.gz';
        $cache = self::$files.'/cache/'.$tar;

        if(file_exists($cache) && !$this->versions->isBranch($this->version)) {
            return $cache;
        }

        if ($this->versions->isBranch($version)) {
            $url = 'http://github.com/joomla/joomla-cms/tarball/'.$version;
        }
        else {
            $url = 'https://github.com/joomla/joomla-cms/archive/'.$version.'.tar.gz';
        }

        $output->writeln("<info>Downloading Joomla $this->version - this could take a few minutes...</info>");
        $bytes = file_put_contents($cache, fopen($url, 'r'));
        if ($bytes === false || $bytes == 0) {
            throw new \RuntimeException(sprintf('Failed to download %s', $url));
        }

        return $cache;
    }
}
