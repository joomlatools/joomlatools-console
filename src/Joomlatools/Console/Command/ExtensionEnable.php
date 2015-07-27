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

class ExtensionEnable extends SiteAbstract
{
    /**
     * Extension name
     *
     * @var string
     */
    protected $extension;

    protected function configure()
    {
        parent::configure();

        $this->setName('extension:enable')
             ->setDescription('Enable any joomla extension')
             ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'testing'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        parent::execute($input, $output);

        $this->extension = $input->getArgument('extension');

        $this->check($input, $output);

        $this->enableExtension($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function enableExtension(InputInterface $input, OutputInterface $output)
    {
        $app = Bootstrapper::getApplication($this->target_dir);

        $dbo = \JFactory::getDbo();
        $query = \JFactory::getDbo()->getQuery(true)
            ->select('extension_id')
            ->from('#__extensions')
            ->where($dbo->quoteName('protected') .' = ' . $dbo->quote(0))
            ->where($dbo->quoteName('name') ." = " . $dbo->quote($this->extension));

        $dbo->setQuery($query);
        $extension = $dbo->loadResult('extension_id');

        if(!count($extension))
        {
            throw new \RuntimeException(sprintf('Extension Uninstall: %s extension not found / Or you are trying to uninstall a core extension.',  $this->extension));
            return;
        }

        require_once $app->getPath().'/administrator/components/com_installer/models/manage.php';

        $manage = new \InstallerModelManage();

        $result = $manage->publish($extension, 0);

        if($result){
            $output->writeln(sprintf("<info>Extension %s was successfully enabled</info>", $this->extension));
        }
        else
        {
            throw new \RuntimeException(sprintf("Enabling extension %s was unsuccessful", $this->extension));
            return;
        }
    }
}