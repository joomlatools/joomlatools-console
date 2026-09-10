<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Compat;

/**
 * --enable-compat / --no-enable-compat, settable in config.yaml like any other option.
 *
 *     globals:
 *       enable-compat: true
 *
 *     site:create:
 *       enable-compat: true
 */
trait EnableCompatTrait
{
    protected function addEnableCompatOption()
    {
        return $this->addOption(
            'enable-compat',
            null,
            InputOption::VALUE_NEGATABLE,
            'Enable Joomla\'s Backward Compatibility plugin (compat on 5, compat6 on 6). Required to install Joomlatools extensions. Use --no-enable-compat to skip.',
            true
        );
    }

    protected function maybeEnableCompat(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('enable-compat')) {
            return;
        }

        $label = Compat::enable($this->target_dir);

        if ($label) {
            $output->writeln(sprintf(
                'Enabled <info>%s</info> (required to install Joomlatools extensions on this Joomla version).',
                $label
            ));
        }
    }
}
