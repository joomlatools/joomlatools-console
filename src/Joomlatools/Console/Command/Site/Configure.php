<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Versions;

class Configure extends AbstractDatabase
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:configure')
            ->setDescription('Create the configuration.php file')
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'Overwrite configuration.php if it already exists'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        $source = $this->target_dir.'/_installation/configuration.php-dist';
        if (!file_exists($source)) {
            $source = $this->target_dir.'/installation/configuration.php-dist';
        }

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

            'mailer'   => 'smtp',
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

        if (file_exists($this->target_dir.'/installation')) {
            `mv $this->target_dir/installation $this->target_dir/_installation`;
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site %s not found', $this->site));
        }

        if (!$input->getOption('overwrite'))
        {
            if (file_exists($this->target_dir . '/configuration.php')) {
                throw new \RuntimeException(sprintf('Site %s is already configured', $this->site));
            }
        }
    }
}