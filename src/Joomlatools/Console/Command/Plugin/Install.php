<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Plugin;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command
{
    protected function configure()
    {
        $this->setName('plugin:install')
             ->setDescription('Install plugin')
            ->setHelp(<<<EOF
You can install plugins into the Joomla Console to add new commands or extend the symlinking logic. Plugins are installed using Composer and must be available on Packagist.
You then pass their package name to this command. In the case of our example, the package name is <comment>joomlatools/console-backup</comment>:

  <info>joomla plugin:install joomlatools/console-backup</info>

You can specify a specific version or branch by appending the version number to the package name. Version constraints follow Composer’s convention:

  <info>joomla plugin:install joomlatools/console-backup:dev-develop</info>

Refer to the online documentation at the following URL on how to write your own plugins: http://developer.joomlatools.com/tools/console/plugins.html#creating-custom-plugins
EOF
            )
             ->addArgument(
                 'package',
                 InputArgument::REQUIRED,
                 'The Composer package name and version. Example: vendor/foo-bar:1.*'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = shell_exec('command -v composer >/dev/null 2>&1 || { echo "false"; }');

        if (trim($result) == 'false')
        {
            $output->writeln('<error>Composer was not found. It is either not installed or globally available</error>');
            return;
        }

        $plugin_path = $this->getApplication()->getPluginPath();

        if (!file_exists($plugin_path)) {
            `mkdir -p $plugin_path`;
        }

        $package = $input->getArgument('package');

        if (strpos($package, ':') === false)
        {
            $name    = $package;
            $version = '';
        }
        else list($name, $version) = explode(':', $package);

        exec("composer show --no-interaction --all $name $version 2>&1", $result, $code);

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

        if ($type != 'joomlatools-console-plugin')
        {
            $output->writeln("<comment>$package is not a Joomla console plugin</comment>");
            $output->writeln('<error>Plugin not installed</error>');
            return;
        }

        passthru("composer --no-interaction --no-progress --working-dir=$plugin_path require $package");
    }
}
