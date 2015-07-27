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

class ExtensionDisable extends ExtensionAbstract
{
    /**
     * Int for enable state
     *
     * @var int
     */
    protected $toggle = 0;
    

    protected function configure()
    {
        parent::configure();

        $this->setName('extension:disable')
             ->setDescription('Disable a joomla extension');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        $this->toggleEnable($input, $output);
    }
}