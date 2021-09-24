<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Extension;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Util;

class Disable extends AbstractExtension
{
    protected function configure()
    {
        parent::configure();

        $this->setName('extension:disable')
             ->setDescription('Disable a Joomla extension');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        if (Util::isJoomla4($this->target_dir)) {
            $output->write("<error>This command is not implemented for Joomla 4</error>\n");

            return;
        }

        $this->check($input, $output);

        $this->toggle(false);

        $output->writeln(sprintf("<info>Extension %s has been disabled</info>", $this->extension));

        return 0;
    }
}