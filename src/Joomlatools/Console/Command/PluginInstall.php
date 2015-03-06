<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginInstall extends Command
{
    protected function configure()
    {
        $this->setName('plugin:install')
             ->setDescription('Install plugin, i.e. joomla console command bundles')
             ->addArgument('package', InputArgument::REQUIRED, 'The Composer package name and version. Example: vendor/foo-bar:1.*');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = `command -v composer >/dev/null 2>&1 || { echo >&2 "false"; }`;

        if ($result != 'false')
        {
            $plugin_path = $this->getApplication()->getPluginPath();

            if (!file_exists($plugin_path)) {
                `mkdir $plugin_path`;
            }

            $package = $input->getArgument('package');

            // Append version if none is set.
            if (strpos($package, ':') === false) {
                $package .= ':dev-master';
            }

            $result = `composer --working-dir=$plugin_path require $package`;

            $output->writeln($result);
        }
        else $output->writeln('<error>Composer was not found. It is either not installed or globally available.</error>');
    }
}