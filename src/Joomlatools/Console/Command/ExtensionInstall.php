<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;
use Joomlatools\Console\Joomla\Util;

class ExtensionInstall extends Site\AbstractSite
{
    protected $extensions = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:install')
            ->setDescription('Install extensions into a site')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of extensions to install to the site using discover install. Use \'all\' to install all discovered extensions.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->extensions = (array) $input->getArgument('extension');

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
        $db  = \JFactory::getDbo();

        // Output buffer is used as a guard against Joomla including ._ files when searching for adapters
        // See: http://kadin.sdf-us.org/weblog/technology/software/deleting-dot-underscore-files.html
        ob_start();

        $installer = $app->getInstaller();
        $installer->discover();

        $path = $app->getPath() . (Util::isPlatform($this->target_dir) ? '/app' : '');
        $path .= '/administrator/components/com_installer/models/discover.php';

        require_once $path;

        $model = new \InstallerModelDiscover();
        $model->discover();

        $results = $model->getItems();

        ob_end_clean();

        $install = array();
        $plugins = array();

        foreach ($this->extensions as $extension)
        {
            foreach ($results as $result)
            {
                $included = false;

                if (in_array($result->element, array('com_extman', 'koowa')) && ($extension == 'all' || $extension == $result->element))
                {
                    array_unshift($install, $result);
                    $included = true;
                }
                elseif ($extension == 'all' || in_array($extension, array($result->element, substr($result->element, 4))))
                {
                    $install[] = $result;
                    $included  = true;
                }

                if ($result->type == 'plugin' && $included) {
                    $plugins[] = $result->extension_id;
                }
            }
        }

        foreach ($install as $extension)
        {
            try {
                $installer->discover_install($extension->extension_id);
            }
            catch (\Exception $e) {
                $output->writeln("<info>Caught exception whilst installing $extension->type $extension->element: " . $e->getMessage() . "</info>\n");
            }

            if (in_array($extension->extension_id, $plugins))
            {
                $sql = "UPDATE `#__extensions` SET `enabled` = 1 WHERE `extension_id` = '$extension->extension_id'";

                $db->setQuery($sql);
                $db->execute();

                switch ($extension->element)
                {
                    case 'com_extman':
                        if(class_exists('Koowa') && !class_exists('ComExtmanDatabaseRowExtension')) {
                            \KObjectManager::getInstance()->getObject('com://admin/extman.database.row.extension');
                        }
                        break;
                    case 'koowa':
                        $path = JPATH_PLUGINS . '/system/koowa/koowa.php';

                        if (!file_exists($path)) {
                            return;
                        }

                        require_once $path;

                        if (class_exists('\PlgSystemKoowa'))
                        {
                            $dispatcher = \JEventDispatcher::getInstance();
                            new \PlgSystemKoowa($dispatcher, (array)\JPLuginHelper::getPLugin('system', 'koowa'));
                        }
                        break;
                }
            }
        }
    }
}