<?php

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class ExtensionInstall extends SiteAbstract
{
    protected $extension = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:install')
            ->setDescription('Install extensions into a site')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of extensions to install to the site using discover install'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->extension = $input->getArgument('extension');

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
        $installer->discover();

        require_once $app->getPath().'/administrator/components/com_installer/models/discover.php';

        $model = new \InstallerModelDiscover();
        $model->discover();

        $results = $model->getItems();

        $install = array();
        foreach ($results as $result)
        {
            if ($result->element === 'com_extman') {
                array_unshift($install, $result->extension_id);
            }

            if ($result->type === 'component' && in_array(substr($result->element, 4), $this->extension)) {
                $install[] = $result->extension_id;
            }
        }

        ob_end_clean();

        if(class_exists('Koowa') && !class_exists('ComExtmanDatabaseRowExtension')) {
            \KObjectManager::getInstance()->getObject('com://admin/extman.database.row.extension');
        }

        $install = array_unique($install);

        foreach ($install as $extension_id) {
            $installer->discover_install($extension_id);
        }
    }
}