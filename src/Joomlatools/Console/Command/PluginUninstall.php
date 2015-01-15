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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginUninstall extends Command
{
    protected function configure()
    {
        $this->setName('plugin:uninstall')
             ->setDescription('Used for uninstalling plugins, i.e. joomla console command bundles')
             ->addArgument('package', InputArgument::REQUIRED, 'The composer package containing the plugin to uninstall');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $package = $input->getArgument('package');

        // Strip package version if set.
        if (strpos($package, ':') !== false) {
            $package = substr($package, 0, strpos($package, ':'));
        }

        $plugins_dir = dirname(__FILE__) . '/../../../../plugins/';

        $composer = $plugins_dir . 'composer.json';

        $contents = file_get_contents($composer);

        if ($contents && ($current = json_decode($contents, true)) && isset($current['require']))
        {
            // TODO: Store error messages for display.
            $errors = array();

            $new = $current;

            $packages = (array) $current['require'];

            foreach ($packages as $name => $version)
            {
                // Delete the package if found in the composer file.
                if (strpos($name, $package) !== false)
                {
                    $parts = explode('/', $package);

                    if (count($parts) == 2)
                    {
                        $package_dir = $plugins_dir . "vendor/{$parts[0]}/{$parts[1]}";

                        // Delete the physical package from vendor.
                        if (file_exists($package_dir)) {
                            `rm -fr $package_dir`; // TODO: Keep track of failed deletes and add a message in errors.
                        }
                    }

                    // Unset the package from the resulting composer file.
                    unset($new['require'][$name]);
                }
            }

            // Update the composer file using the new json data.
            if (isset($new) && array_diff($current['require'], $new['require']))
            {
                // Cleanup json data.
                if (empty($new['require'])) {
                    unset($new['require']);
                }

                if ($contents = json_encode($new, JSON_FORCE_OBJECT)) {
                    file_put_contents($composer, $contents); // TODO: Keep track of failed deletes and add a message in errors.
                }

                if ($errors)
                {
                    $output->writeln('<error>The package could not be deleted because:</error>');

                    foreach ($errors as $error) {
                        $output->writeln("<comment>{$error}</comment>");
                    }
                }
                else $output->write('<info>The package was successfully deleted</info>');
            }
            else $output->writeln('<comment>The package is not installed. Nothing to delete.</comment>');
        }
        else $output->writeln('<comment>There are no plugins installed yet. Nothing to delete.</comment>');
    }
}