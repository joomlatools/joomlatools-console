<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use Joomlatools\Console\Joomla\Deserializer;
use Joomlatools\Console\Joomla\Util;
use Joomlatools\Console\Command\Database\AbstractDatabase;

class Configure extends AbstractDatabase
{
    /**
     * Flag to keep track of whether we still need to
     * prompt the database settings in interactive mode
     *
     * @var bool
     */
    protected $_skip_database_prompt = false;

    /**
     * List of default values
     *
     * @var array
     */
    protected $_default_values = array();

	/**
	 * Additional options read from file
	 *
	 * @var array
	 */
    protected $_extra_options = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:configure')
            ->setDescription('Configure a Joomla site')
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'Overwrite configuration.php or .env file if it already exists'
            )
            ->addOption(
                'interactive',
                null,
                InputOption::VALUE_NONE,
                'Prompt for configuration details'
            )
	        ->addOption(
	        	'options',
		        null,
		        InputOption::VALUE_REQUIRED,
		        "A YAML file consisting of serialized parameters to override JConfig"
	        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $random = function($length) {
            $charset ='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $string  = '';
            $count   = strlen($charset);

            while ($length--) {
                $string .= $charset[mt_rand(0, $count-1)];
            }

            return $string;
        };

        $this->_default_values = array(
            'log_path' => $this->target_dir . '/logs/',
            'tmp_path' => $this->target_dir . '/tmp/',
            'sitename' => $this->site,
            'key'      => $random(16),
            'env'      => 'development'
        );

        if ($input->getOption('interactive')) {
            $this->_promptDetails($input, $output);
        }

        $options = $input->getOption('options');
        if ($options !== null)
        {
            if (!file_exists($options)) {
                throw new Exception(sprintf('Additional option file \'%s\' does not exist', $options));
            }

            $contents = file_get_contents($options);

            try {
                $this->_extra_options = Yaml::parse($contents);
            }
            catch (Exception $ex) {
                throw new Exception(sprintf('Unable to parse YAML file %s', $options));
            }
        }

        $this->check($input, $output);

        if (Util::isPlatform($this->target_dir)) {
            $this->_configureJoomlatoolsPlatform();
        } else if (Util::isKodekitPlatform($this->target_dir)) {
            $this->_configureKodekitPlatform();
        } else {
            $this->_configureJoomlaCMS();
        }
    }

    protected function _configureJoomlaCMS()
    {
        $source = $this->target_dir.'/_installation/configuration.php-dist';
        if (!file_exists($source)) {
            $source = $this->target_dir.'/installation/configuration.php-dist';
        }

        $target   = $this->target_dir.'/configuration.php';

        $contents = file_get_contents($source);
        $replace  = function($name, $value, &$contents) {
            $pattern = sprintf('#\$%s\s*=\s*(["\']?).*?\1(?=[;\1])#', $name);
            $match   = preg_match($pattern, $contents);
            $value   = is_numeric($value) ? $value : "'" . str_replace("'", "\\'", $value) . "'";

            if(!$match)
            {
                $pattern 	 = "/^\s*}\s*$/m";
                $replacement = sprintf("\tpublic \$%s = %s;\n}", $name, $value);
            }
            else $replacement = sprintf("\$%s = %s", $name, $value);

            $contents = preg_replace($pattern, $replacement, $contents);
        };
        $remove   = function($name, &$contents) {
            $pattern  = sprintf('#public\s+\$%s\s*=\s*(["\']?).*?\1(?=[;\1])\s*;#', $name);
            $contents = preg_replace($pattern, '', $contents);
        };

        $replacements = array(
            'db'        => $this->target_db,
            'user'      => $this->mysql->user,
            'password'  => $this->mysql->password,
            'host'      => $this->mysql->host,
            'dbprefix'  => 'j_',
            'dbtype'    => $this->mysql->driver,

            'mailer'   => 'smtp',
            'mailfrom' => 'admin@example.com',
            'fromname' => $this->site,
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
            'tmp_path'  => $this->_default_values['tmp_path'],
            'log_path'  => $this->_default_values['log_path'],
            'sitename'  => $this->_default_values['sitename'],

            'secret'    => $this->_default_values['key']
        );

	    if ($this->mysql->port != $this->getDefaultPort()) {
	    	$replacements['host'] .= ':' . $this->mysql->port;
        }

        $configuration = array_merge($replacements, $this->_extra_options);
        foreach($configuration as $key => $value) {
            $replace($key, $value, $contents);
        }

        $remove('root_user', $contents);

        file_put_contents($target, $contents);
        chmod($target, 0664);

        if (file_exists($this->target_dir.'/installation')) {
            `mv $this->target_dir/installation $this->target_dir/_installation`;
        }
    }

    protected function _configureJoomlatoolsPlatform()
    {
        $config = array(
            'JOOMLA_DB_NAME' => $this->target_db,
            'JOOMLA_DB_USER' => $this->mysql->user,
            'JOOMLA_DB_PASS' => $this->mysql->password,
            'JOOMLA_DB_HOST' => $this->mysql->host,
            'JOOMLA_DB_TYPE' => $this->mysql->driver,

            'JOOMLA_LOG_PATH' => $this->_default_values['log_path'],
            'JOOMLA_TMP_PATH' => $this->_default_values['tmp_path'],

            'JOOMLA_KEY' => $this->_default_values['key'],
            'JOOMLA_ENV' => $this->_default_values['env']
        );

        if ($this->mysql->port != $this->getDefaultPort()) {
        	$config['JOOMLA_DB_HOST'] .= ':' . $this->mysql->port;
        }

        $fp = fopen($this->target_dir.'/.env', 'w');

        foreach (array_merge($config, $this->_extra_options) as $key => $val) {
            fwrite($fp, $key . '=' . $val . PHP_EOL);
        }

        fclose($fp);
    }

    protected function _configureKodekitPlatform()
    {
        $config = require $this->target_dir.'/config/bootstrapper.php-empty';

        $dbidentifier = 'database.driver.'.$this->mysql->driver;
        $dbhost       = $this->mysql->host;

        if ($this->mysql->port != $this->getDefaultPort()) {
            $dbhost .= ':' . $this->mysql->port;
        }

        $settings =  array(
            'identifiers' => array(
                'application'  => array(
                    'title'    => $this->_default_values['sitename'],
                    'mailer'    => 'smtp',
                    'mailfrom'  => 'admin@example.com',
                    'fromname'  => $this->site,
                    'smtphost'  => 'localhost',
                    'smtpport'  => 1025,
                    'debug'     => 1
                ),
                $dbidentifier => array(
                    'auto_connect' => true,
                    'database'     => $this->target_db,
                    'host'         => $dbhost,
                    'username'     => $this->mysql->user,
                    'password'     => $this->mysql->password,
                )
            )
        );

        $config = array_replace_recursive($config, $settings);
        $config = array_replace_recursive($config, $this->_extra_options);

        $export       = '<?php ' . PHP_EOL . 'return ' . var_export($config, true) . ';';
        $bootstrapper = $this->target_dir.'/config/bootstrapper.php';

        file_put_contents($bootstrapper, $export);
    }

	/**
	 * Get default port for MySQL
	 *
	 * @return string
	 */
    protected function getDefaultPort()
    {
	    $driver = $this->mysql->driver;
	    $key    = $driver . '.default_port';
	    $port   = ini_get($key);

	    if ($port) {
	    	return $port;
	    }

	    return ini_get('mysqli.default_port');
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site %s not found', $this->site));
        }

        if (!$input->getOption('overwrite'))
        {
            $file = Util::isPlatform($this->target_dir) ? '.env' : 'configuration.php';

            if (file_exists($this->target_dir . '/' . $file)) {
                throw new \RuntimeException(sprintf('Site %s is already configured', $this->site));
            }
        }
    }

    /**
     * Tell the object to skip the database prompts
     * in interactive mode or not.
     *
     * @param $value bool
     */
    public function skipDatabasePrompt($value = true)
    {
        $this->_skip_database_prompt = $value;
    }

    protected function _promptDetails(InputInterface $input, OutputInterface $output)
    {
        if (!$this->_skip_database_prompt) {
            $this->_promptDatabaseDetails($input, $output);
        }

        if (Util::isPlatform($this->target_dir)) {
            $this->_default_values['env'] = $this->_ask($input, $output, 'Environment', array('development', 'staging', 'production'), true);
        }
        else {
            $this->_default_values['sitename'] = $this->_ask($input, $output, 'Site Name', $this->_default_values['sitename'], true);
        }

        $this->_default_values['tmp_path'] = $this->_ask($input, $output, 'Temporary path', $this->_default_values['tmp_path'], true);
        $this->_default_values['log_path'] = $this->_ask($input, $output, 'Log path', $this->_default_values['log_path'], true);
        $this->_default_values['key']      = $this->_ask($input, $output, 'Secret Key', $this->_default_values['key'], true);
    }
}
