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

use Joomlatools\Console\Joomla\Bootstrapper;

abstract class ExtensionAbstract extends Command
{
    protected function configure()
    {
        $this->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Alphanumeric site name. Also used in the site URL with .dev domain'
            )->addArgument(
                'extension',
                InputArgument::REQUIRED,
                ''
            )->addOption(
                'www',
                null,
                InputOption::VALUE_REQUIRED,
                "Web server root",
                '/var/www'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site       = $input->getArgument('site');
        $this->www        = $input->getOption('www');
        $this->target_dir = $this->www.'/'.$this->site;
        $this->extension = $input->getArgument('extension');
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function toggleEnable(InputInterface $input, OutputInterface $output)
    {
        $app = Bootstrapper::getApplication($this->target_dir);

        $dbo = \JFactory::getDbo();
        $query = \JFactory::getDbo()->getQuery(true)
            ->select('extension_id')
            ->from('#__extensions')
            ->where($dbo->quoteName('name') ." = " . $dbo->quote($this->extension));

        $dbo->setQuery($query);
        $extension = $dbo->loadResult('extension_id');

        require_once $app->getPath().'/administrator/components/com_installer/models/manage.php';

        $manage = new \InstallerModelManage();
        $manage->publish($extension, $this->toggle);

        $app = \JFactory::getApplication();
        $messages = $app->getMessageQueue();
        $state = $this->toggle ? 'enabled' : 'disabled';

        if (is_array($messages) && count($messages))
        {
            foreach ($messages as $msg)
            {
                if (isset($msg['type']) && isset($msg['message']))
                {
                    throw new \RuntimeException(sprintf('Extension %s: %s', $this->extension, $msg['message']));
                }
            }
        }
        else
            $output->writeln(sprintf("<info>Extension %s was successfully %s</info>", $this->extension, $state));
    }
}