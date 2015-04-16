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

        if ($result == 'false')
        {
            $output->writeln('<error>Composer was not found. It is either not installed or globally available</error>');
            return;
        }

        $plugin_path = $this->getApplication()->getPluginPath();

        if (!file_exists($plugin_path)) {
            `mkdir $plugin_path`;
        }

        $package = $input->getArgument('package');

        // Append version if none is set.
        if (strpos($package, ':') === false) {
            $package .= ':dev-master';
        }

        list($name, $version) = explode(':', $package);

        exec("composer show $name $version 2>&1", $result, $code);

        if ($code === 1)
        {
            $output->writeln("<error>The $package plugin you are attempting to install cannot be found</error>");
            return;
        }

        $type = '';

        foreach ($result as $line => $content)
        {
            $content = trim($content);

            if (strpos($content, 'type') === 0) {
                $parts = explode(':', $content);

                if (count($parts) > 1)
                {
                    $type = trim($parts[1]);
                    break;
                }
            }
        }

        if ($type != 'joomla-console-plugin')
        {
            $output->writeln("<comment>$package is not a Joomla console plugin</comment>");
            $output->writeln('<error>Plugin not installed</error>');
            return;
        }

        passthru("composer --no-progress --working-dir=$plugin_path require $package");
    }
}