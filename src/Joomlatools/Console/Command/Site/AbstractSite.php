<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractSite extends Command
{
    protected $site;
    protected $www;

    protected $target_dir;

    protected static $files;

    protected function configure()
    {
        if (empty(self::$files)) {
            self::$files = realpath(__DIR__.'/../../../../../bin/.files');
        }

        $this->addArgument(
            'site',
            InputArgument::REQUIRED,
            'Alphanumeric site name. Also used in the site URL with .dev domain'
        )->addOption(
            'www',
            null,
            InputOption::VALUE_REQUIRED,
            "Web server root",
            '/var/www'
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site       = $input->getArgument('site');
        $this->www        = $input->getOption('www');
        $this->target_dir = $this->www.'/'.$this->site;
    }

    protected function _getJoomlaVersion()
    {
        $code = $this->target_dir . '/libraries/cms/version/version.php';

        if (file_exists($code))
        {
            if (!defined('JPATH_PLATFORM')) {
                define('JPATH_PLATFORM', $this->target_dir.'/libraries');
            }

            if (!defined('_JEXEC')) {
                define('_JEXEC', 1);
            }

            $identifier = uniqid();

            $source = file_get_contents($code);
            $source = preg_replace('/<\?php/', '', $source, 1);
            $source = preg_replace('/class JVersion/i', 'class JVersion' . $identifier, $source);

            eval($source);

            $class   = 'JVersion'.$identifier;
            $version = new $class();

            return $version->RELEASE.'.'.$version->DEV_LEVEL;
        }

        return false;
    }
}
