<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Extension;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;
use Joomlatools\Console\Joomla\Bootstrapper;

class Install extends AbstractSite
{
    protected $extensions          = array();
    protected $composer_extensions = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:install')
            ->setDescription('Install extensions into a site using the discover method')
            ->setHelp(<<<EOL
After copying or symlinking your extension code into the Joomla application, you can use this command to
have Joomla automatically find the extension files and install it:

    <info>joomla extension:install testsite com_foobar</info>

The extension argument should match the element name (<comment>com_foobar</comment>) as defined in your extension XML manifest.

For more information about Joomla's discover method, refer to the official documentation: https://docs.joomla.org/Help34:Extensions_Extension_Manager_Discover

Alternatively simply pass in the Composer packages you would like to install: provide these in the format (vendor/package:[commit || [operator version]) 
EOL
            )
            ->addArgument(
                'extension',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of extensions to install to the site using discover install. Use \'all\' to install all discovered extensions.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $extensions = (array) $input->getArgument('extension');

        $this->composer_extensions = array_filter(array_map(function($x){
            if(strpos($x, '/') !== false){
                return $x;
            }
        }, $extensions));

        if(count($this->composer_extensions)) {
            $extensions = array_diff($extensions, $this->composer_extensions);
        }

        $this->extensions = $extensions;

        $this->check($input, $output);

        if (count($this->extensions)) {
            $this->installFiles($input, $output);
        }

        if (count($this->composer_extensions)) {
            $this->installPackages($input, $output);
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        if (count($this->composer_extensions))
        {
            $result = shell_exec('composer -v > /dev/null 2>&1 || { echo "false"; }');

            if (trim($result) == 'false')
            {
                $output->writeln('<error>You need Composer installed globally. See: https://getcomposer.org/doc/00-intro.md#globally</error>');
                exit();
            }
        }
    }

    public function installPackages(InputInterface $input, OutputInterface $output)
    {
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln("Installing Composer packages ..");
        }

        $packages = array_map('escapeshellarg', $this->composer_extensions);
        $command  = sprintf('composer --no-interaction --no-progress require %s --working-dir=%s', implode(' ', $packages), escapeshellarg($this->target_dir));

        passthru($command, $result);

        if ((int) $result) {
            throw new \RuntimeException('Failed to install Composer packages');
        }
    }

    public function installFiles(InputInterface $input, OutputInterface $output)
    {
        $app = Bootstrapper::getApplication($this->target_dir);
        $db  = \JFactory::getDbo();

        // Output buffer is used as a guard against Joomla including ._ files when searching for adapters
        // See: http://kadin.sdf-us.org/weblog/technology/software/deleting-dot-underscore-files.html
        ob_start();

        $installer = $app->getInstaller();
        $installer->discover();

        require_once JPATH_ADMINISTRATOR . '/components/com_installer/models/discover.php';

        $model = new \InstallerModelDiscover();
        $model->discover();

        // Trigger the populateState() method in the model
        $model->getState('list.limit');
        // then override the default limit to 999
        $model->setState('list.limit', 999);

        $results = $model->getItems();

        ob_end_clean();

        $install = array();
        $plugins = array();

        foreach ($this->extensions as $extension)
        {
            foreach ($results as $result)
            {
                $included = false;

                if (($result->element === 'joomlatools' && $result->type === 'plugin' && $result->folder === 'system')
                    && ($extension == 'all' || $extension == 'joomlatools-framework' || $extension == $result->element)
                )
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

                if ($included && $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln("Queued $result->name for installation.");
                }
            }
        }

        foreach ($install as $extension)
        {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln("Installing $extension->element ..");
            }

            try {
                $installer->discover_install($extension->extension_id);
            }
            catch (\Exception $e) {
                $output->writeln("<info>Caught exception whilst installing $extension->type $extension->element: " . $e->getMessage() . "</info>\n");
            }

            if (in_array($extension->extension_id, $plugins))
            {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln("Enabling plugin `$extension->element` (ID #$extension->extension_id) ..");
                }

                $sql = "UPDATE `#__extensions` SET `enabled` = 1 WHERE `extension_id` = '$extension->extension_id'";

                $db->setQuery($sql);
                $db->execute();

                if ($extension->element === 'joomlatools' && $extension->type === 'plugin' && $extension->folder === 'system')
                {
                    $path = JPATH_PLUGINS . '/system/joomlatools/joomlatools.php';

                    if (!file_exists($path)) {
                        return;
                    }

                    require_once $path;

                    if (class_exists('\PlgSystemJoomlatools'))
                    {
                        $dispatcher = \JEventDispatcher::getInstance();
                        new \PlgSystemJoomlatools($dispatcher, (array)\JPLuginHelper::getPLugin('system', 'joomlatools'));
                    }

                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $output->writeln("Initialised new PlgSystemJoomlatools instance");
                    }
                }
            }
        }
    }
}