<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Joomlatools\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question;
use Joomlatools\Console\Joomla\Util;

abstract class AbstractSite extends Command\Configurable
{
    protected $site;
    protected $www;

    protected $target_dir;

    protected static $files;

    protected $_config = null;

    protected function configure()
    {
        if (empty(self::$files)) {
            self::$files = Util::getTemplatePath() . '/bin/.files';
        }

        $this->addArgument(
            'site',
            InputArgument::REQUIRED,
            'Alphanumeric site name. Also used in the site URL with .test domain'
        )->addOption(
            'www',
            null,
            InputOption::VALUE_REQUIRED,
            "Web server root",
            '/var/www'
        )
        ->addOption(
            'use-webroot-dir',
            null,
            InputOption::VALUE_NONE,
            "Uses directory specified with --www as the site install dir"
         )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site       = $input->getArgument('site');
        $this->www        = $input->getOption('www');

        if ($input->getOption('use-webroot-dir')) {
            $this->target_dir = $this->www;
        } else {
            $this->target_dir = $this->www.'/'.$this->site;
        }

        return 0;
    }

    /**
     * Prompt user to fill in a value
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $label    string          The description of the value
     * @param $default  string|array    The default value. If array given, question will be multiple-choice and the first item will be default. Can also be empty.
     * @param bool $required
     * @param bool $hidden  Hide user input (useful for passwords)
     *
     * @return string   Answer
     */
    protected function _ask(InputInterface $input, OutputInterface $output, $label, $default = '', $required = false, $hidden = false)
    {
        $helper  = $this->getHelper('question');
        $text    = $label;

        if (is_array($default)) {
            $defaultValue = $default[0];
        }
        else $defaultValue = $default;

        if (!empty($defaultValue)) {
            $text .= ' [default: <info>' . $defaultValue . '</info>]';
        }

        $text .= ': ';

        if (is_array($default)) {
            $question = new Question\ChoiceQuestion($text, $default, 0);
        }
        else $question = new Question\Question($text, $default);

        if ($hidden === true) {
            $question->setHidden(true);
        }

        $answer = $helper->ask($input, $output, $question);

        if ($required && empty($answer)) {
            return $this->_ask($input, $output, $label, $default, $hidden);
        }

        return $answer;
    }

    /**
     * Resolve the path to Apache's main config file via apachectl -V.
     *
     * Shared by _getDefaultVhostFolder(), _getDefaultLogFolder() and
     * Vhost\Create::_warnIfVhostFolderNotIncluded() so the HTTPD_ROOT/SERVER_CONFIG_FILE
     * parsing lives in one place.
     *
     * @return string|null
     */
    protected function _getApacheConfigFile()
    {
        exec('apachectl -V 2>/dev/null', $lines);

        $root = $config = null;
        foreach ($lines as $line) {
            if (preg_match('/HTTPD_ROOT="(.+)"/', $line, $matches)) {
                $root = $matches[1];
            }
            if (preg_match('/SERVER_CONFIG_FILE="(.+)"/', $line, $matches)) {
                $config = $matches[1];
            }
        }

        if (!$config) {
            return null;
        }

        if ($config[0] !== '/' && $root) {
            $config = $root.'/'.$config;
        }

        return $config;
    }

    /**
     * Determine a writable Apache vhost folder without requiring sudo.
     *
     * The traditional /etc/apache2/sites-enabled path is only writable without sudo on
     * Linux systems where Apache runs/is owned by the current user (eg. Docker, Vagrant).
     * On macOS with a Homebrew-installed Apache, config lives under the Homebrew prefix
     * and is owned by the current user instead, so fall back to detecting it via apachectl.
     *
     * @return string
     */
    protected function _getDefaultVhostFolder()
    {
        $default = '/etc/apache2/sites-enabled';

        if (is_dir($default) && is_writable($default)) {
            return $default;
        }

        $config = $this->_getApacheConfigFile();

        if ($config && is_writable(dirname($config))) {
            return dirname($config).'/sites-enabled';
        }

        return $default;
    }

    /**
     * Determine a writable Apache log folder without requiring sudo.
     *
     * Mirrors _getDefaultVhostFolder(): the traditional /var/log/apache2 path only exists
     * on Linux. On macOS with a Homebrew-installed Apache, logs live under the Homebrew
     * prefix instead, so fall back to reading the main ErrorLog directive from httpd.conf.
     *
     * @return string
     */
    protected function _getDefaultLogFolder()
    {
        $default = '/var/log/apache2';

        if (is_dir($default) && is_writable($default)) {
            return $default;
        }

        $config = $this->_getApacheConfigFile();

        if ($config && file_exists($config) && preg_match('/^\s*ErrorLog\s+"?([^"\s]+)"?/mi', file_get_contents($config), $matches)) {
            return dirname($matches[1]);
        }

        return $default;
    }
}
