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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Util;

class Uninstall extends Command
{
    protected function configure()
    {
        $this->setName('plugin:uninstall')
             ->setDescription('Used for uninstalling plugins')
             ->addArgument(
                 'package',
                 InputArgument::REQUIRED,
                 'The composer package containing the plugin to uninstall'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (Util::isJoomla4($this->target_dir)) {
            $output->write("<error>This command is not implemented for Joomla 4</error>\n");

            return;
        }

        $plugins = $this->getApplication()->getPlugins();
        $path    = $this->getApplication()->getPluginPath();

        $package = $input->getArgument('package');

        $result = `command -v composer >/dev/null 2>&1 || { echo >&2 "false"; }`;

        if ($result == 'false')
        {
            $output->writeln('<error>Composer was not found. It is either not installed or globally available.</error>');
            return 99;
        }

        if (!array_key_exists($package, $plugins))
        {
            $output->writeln('<error>Error:</error>The package "' . $package . '" is not installed');
            return 99;
        }

        passthru("composer --no-interaction --working-dir=$path remove $package");

        return 0;
    }
}
