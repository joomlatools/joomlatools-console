<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class ExtensionUninstall extends SiteAbstract
{
    protected $extensions = array();

    protected $type = '';

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:uninstall')
            ->setDescription('Uninstall un-protected extensions from a site')
            ->addArgument(
                'extensions',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of extensions to un-install to the site using discover install'
            )->addOption(
                'type',
                't',
                InputOption::VALUE_OPTIONAL,
                'Specify the type of extension to remove'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->extensions = $input->getArgument('extensions');
        $this->type = $input->getOption('type');

        $this->check($input, $output);
        $this->install($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function install(InputInterface $input, OutputInterface $output)
    {
        $app = Bootstrapper::getApplication($this->target_dir);

        // Output buffer is used as a guard against Joomla including ._ files when searching for adapters
        // See: http://kadin.sdf-us.org/weblog/technology/software/deleting-dot-underscore-files.html
        ob_start();

        $installer = $app->getInstaller();

        $dbo = \JFactory::getDbo();
        $query = \JFactory::getDbo()->getQuery(true)
            ->select('*')
            ->from('#__extensions')
            ->where('protected = 0');

        if(strlen($this->type)){
            $query->where($dbo->quoteName('type') . " = " . $dbo->quote($this->type));
        }

        if(count($this->extensions)){
            $query->where("(" .$dbo->quoteName('name') ." = " . implode(" OR " .$dbo->quoteName('name') . " = ", $dbo->quote($this->extensions)) . ")");
        }

        $dbo->setQuery($query);
        $extensions = $dbo->loadObjectList();

        if(!count($extensions))
        {
            $output->writeln('<error>Component Uninstall: Component not found / Or you are trying to uninstall a core component.</error>');
            return;
        }

        foreach ($extensions as $extension)
        {
            $result = $installer->uninstall($extension->type, $extension->extension_id);

            if($result){
                $output->writeln('<info>' . $extension->name . ' extension deleted </info>');
            }else
                $output->writeln('<error> Problem deleting ' . $extension->name . '</error>');
        }

        ob_end_clean();
    }
}