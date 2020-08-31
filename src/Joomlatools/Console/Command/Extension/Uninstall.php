<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Extension;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;

use Joomlatools\Console\Joomla\Bootstrapper;

class Uninstall extends AbstractSite
{
    protected $extensions = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:uninstall')
            ->setDescription('Uninstall un-protected extensions from a site')
            ->addArgument(
                'extensions',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'The 3rd party extensions to uninstall from the site'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->extensions = $input->getArgument('extensions');

        $this->check($input, $output);
        $this->uninstall($input, $output);

        return 0;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function uninstall(InputInterface $input, OutputInterface $output)
    {
        $app = Bootstrapper::getApplication($this->target_dir);

        ob_start();

        $installer = $app->getInstaller();

        foreach($this->extensions as $extension)
        {
            $dbo = \JFactory::getDbo();
            $query = \JFactory::getDbo()->getQuery(true)
                ->select('*')
                ->from('#__extensions')
                ->where($dbo->quoteName('element') . ' = ' . $dbo->quote($extension));

            $dbo->setQuery($query);
            $row = $dbo->loadObject();

            if(!$row || !$row->extension_id) {
                throw new \RuntimeException(sprintf('Extension Uninstall: %s extension not found',  $extension));
            }

            if($row->protected) {
                throw new \RuntimeException(sprintf('Extension Uninstall: %s is a core extension',  $extension));
            }

            $result = $installer->uninstall($row->type, $row->extension_id);

            if ($result) {
                $output->writeln('<info>' . $row->name . ' extension deleted </info>');
            }
            else throw new \RuntimeException(sprintf('Extension Uninstall: failed to delete %s extension', $extension));
        }

        ob_end_clean();
    }
}