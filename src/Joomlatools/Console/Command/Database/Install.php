<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Util;

class Install extends AbstractDatabase
{
    /**
     * Sample data to install
     *
     * @var string
     */
    protected $sample_data;

    /**
     * Flag to drop database if it exists
     *
     * @var boolean
     */
    protected $drop = false;

    /**
     * Flag to skip checking if database exists
     *
     * @var boolean
     */
    protected $skip_check = false;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('database:install')
            ->setDescription('Install the Joomla database')
            ->addOption(
                'sample-data',
                null,
                InputOption::VALUE_REQUIRED,
                'Sample data to install (default|blog|brochure|learn|testing). Ignored if custom dump files are given using --sql-dumps.'
            )
            ->addOption(
                'sql-dumps',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Full path to SQL dump file to import. If not set, the command will use the default Joomla installation files.',
                array()
            )
            ->addOption(
                'drop',
                'd',
                InputOption::VALUE_NONE,
                'Drop database if it already exists'
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

        $this->drop        = $input->getOption('drop');
        $this->skip_check  = $input->getOption('skip-exists-check');

        $this->check($input, $output);

        if ($this->drop) {
            $this->_executeSQL(sprintf("DROP DATABASE IF EXISTS `%s`", $this->target_db));
        }

        $result = $this->_executeSQL(sprintf("CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8", $this->target_db));

        if (!empty($result)) {
            throw new \RuntimeException(sprintf('Cannot create database %s. Error: %s', $this->target_db, $result));
        }

        $imports = $this->_getSQLFiles($input, $output);

        foreach($imports as $import)
        {
            $tmp      = tempnam('/tmp', 'dump');
            $contents = file_get_contents($import);
            $contents = str_replace('#__', 'j_', $contents);

            file_put_contents($tmp, $contents);

            $password = empty($this->mysql->password) ? '' : sprintf("-p'%s'", $this->mysql->password);
            $result = exec(sprintf("mysql --host=%s --port=%u --user='%s' %s %s < %s", $this->mysql->host, $this->mysql->port, $this->mysql->user, $password, $this->target_db, $tmp));

            unlink($tmp);

            if (!empty($result)) {
                throw new \RuntimeException(sprintf('Cannot import database "%s". Error: %s', basename($import), $result));
            }
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site %s not found', $this->site));
        }

        if ($this->drop === false && $this->skip_check === false)
        {
            $result = $this->_executeSQL(sprintf("SHOW DATABASES LIKE \"%s\"", $this->target_db));

            if (!empty($result)) {
                throw new \RuntimeException(sprintf('A database with name %s already exists', $this->target_db));
            }
        }

        $sample_data = $input->getOption('sample-data');
        if ($sample_data)
        {
            if (!in_array($sample_data, array('default', 'blog', 'brochure', 'testing', 'learn'))) {
                throw new \RuntimeException(sprintf('Unknown sample data "%s"', $this->sample_data));
            }

            $version = Util::getJoomlaVersion($this->target_dir);

            if($version !== false && $version->release)
            {
                if (in_array($sample_data, array('testing', 'learn')) && version_compare($version->release, '3.0.0', '<')) {
                    throw new \RuntimeException(sprintf('%s does not support sample data %s', $version->release, $sample_data));
                }
            }
        }
    }

    protected function _getSQLFiles(InputInterface $input, OutputInterface $output)
    {
        $dumps = $input->getOption('sql-dumps');

        if (count($dumps) > 0)
        {
            foreach ($dumps as $dump)
            {
                if (!file_exists($dump)) {
                    throw new \RuntimeException(sprintf('Can not find SQL dump file %s', $dump));
                }
            }

            return $dumps;
        }

        $version = Util::getJoomlaVersion($this->target_dir);
        $imports = $this->_getInstallFiles($input->getOption('sample-data'));

        $isJoomlaCMS = !Util::isPlatform($this->target_dir) && !Util::isKodekitPlatform($this->target_dir);

        if ($version !== false && $isJoomlaCMS)
        {
            $users = 'joomla3.users.sql';
            if(is_numeric(substr($version->release, 0, 1)) && version_compare($version->release, '3.0.0', '<')) {
                $users = 'joomla2.users.sql';
            }
            $path = Util::getTemplatePath();

            $imports[] = $path.'/'.$users;
        }

        foreach ($imports as $import)
        {
            if (!file_exists($import)) {
                throw new \RuntimeException(sprintf('Can not find SQL dump file %s', $import));
            }
        }

        return $imports;
    }

    protected function _getInstallFiles($sample_data = false)
    {
        $files = array();

        if (Util::isPlatform($this->target_dir) || Util::isKodekitPlatform($this->target_dir))
        {
            $path = $this->target_dir .'/install/mysql/';

            $files[] = $path . 'schema.sql';
            $files[] = $path . 'data.sql';
        }
        else
        {
            $path = $this->target_dir.'/_installation/sql/mysql/';

            if (!file_exists($path)) {
                $path = $this->target_dir.'/installation/sql/mysql/';
            }

            $files[] = $path.'joomla.sql';

            if ($sample_data)
            {
                $type      = $sample_data == 'default' ? 'data' : $sample_data;
                $sample_db = $path . 'sample_' . $type . '.sql';

                $files[] = $sample_db;
            }
        }

        return $files;
    }
}
