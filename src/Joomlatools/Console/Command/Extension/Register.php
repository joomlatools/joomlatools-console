<?php
/**
 * @copyright    Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license        Mozilla Public License, version 2.0
 * @link        http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Extension;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class Register extends AbstractExtension
{
    /**
     * type of extension
     *
     * @var string
     */
    protected $type = '';

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:register')
            ->setDescription('Register an extension with the `#__extensions` table.')
            ->setHelp(<<<EOL
You can register your extension in the extensions table without the need for a complete install package containing a valid XML manifest file.

    <info>joomla extension:register testsite com_foobar</info>

The type of extension that gets registered is based on the first 4 characters of the extension argument you pass in. Example:

* com_ => component
* mod_ => module
* plg_ => plugin (the plg_ will get stripped from the element field)
* lib_ => library
* pkg_ => package
* tpl_ => template (the tpl_ will get stripped from the name and element field)
* lng_ => language

This example registers an extension of the ‘plugin’ type:

    <info>joomla extension:register testsite plg_foobar</info>

You can use naming without the type prefixes by adding a type argument to the end of the command:

    <info>joomla extension:register testsite foobar package</info>

In all cases, if the type is not specified or recognized then the default value, component, will be used.

When registering a plugin type you can use the <comment>--folder</comment> option to specify the plugin group that will get registered with the record. This defaults to <comment>system</comment>. Example:

    <info>joomla extension:register testsite foobar plugin --folder=content</info>

For a language type extension, you should use the <comment>--element</comment> option to ensure your language files can be loaded correctly:

    <info>joomla extension:register testsite spanglish language --element=en-GB</info>

When registering a module type extension, you can use the <comment>--position</comment> option to ensure your module displays where you would like it to. A record gets added to the <comment>#_modules</comment> table:

    <info>joomla extension:register testsite mod_foobar --position=debug</info>
EOL
            )
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Type of extension being registered.')
            ->addOption(
                'folder',
                null,
                InputOption::VALUE_REQUIRED,
                'Specifically for the Plugin typed extension, default "system"'
            )->addOption(
                'enabled',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enabled or not, default is "1"',
                1
            )->addOption(
                'client_id',
                null,
                InputOption::VALUE_REQUIRED,
                '"0" for Site, "1" for Administrator'
            )->addOption(
                'element',
                null,
                InputOption::VALUE_REQUIRED,
                "Provide the element name for languages"
            )->addOption(
                'position',
                null,
                InputOption::VALUE_REQUIRED,
                "Provide the position the module should appear"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $type = false;

        // passed in type argument
        $forceType = $input->getArgument('type');

        // Try to load the type based on naming convention if we aren't passing a 'type' argument
        if (!$forceType)
        {
            $prefix = substr($this->extension, 0, 4);
            $type = isset($this->typeMap[$prefix]) ? $this->typeMap[$prefix] : false;
        }

        // only allow ones that exist.
        if (in_array($forceType, $this->typeMap)) {
            $type = $forceType;
        }

        // set the type.
        if (!$type)
        {
            $output->writeln("<comment>'{$type}' is not allowed as an extension type. Changing to 'component'</comment>");
            $this->type = 'component';
        }
        else $this->type = $type;

        $this->check($input, $output);
        $this->register($input, $output);

        return 0;
    }

    public function register(InputInterface $input, OutputInterface $output)
    {
        $app = Bootstrapper::getApplication($this->target_dir);

        ob_start();

        // build the record.
        $data = new \JObject;
        $data->name = $this->extension;
        $data->type = $this->type;
        $data->element = $this->extension;
        $data->client_id = $input->getOption('client_id');
        $data->enabled = $input->getOption('enabled');
        $data->position = $input->getOption('position');

        $element = $input->getOption('element');
        if(strlen($element)){
            $data->element = $element;
        }

        // special case for plugin, naming and folder.
        if($this->type == 'plugin')
        {
            // set the default folder for plugins only.
            $data->folder = $input->getOption('folder') ? $input->getOption('folder') : 'system';

            // special case for the plugins only.
            if(substr($data->element, 0, 4) == 'plg_') {
                $data->element = substr($data->element, 4);
            }
        }

        if($this->type == 'template')
        {
            if(substr($data->name, 0, 4) == 'tpl_') {
                $data->name = substr($data->name, 4);
                $data->element = substr($data->element, 4);
            }
        }

        //need to be sure that a prefix is provided for components and modules
        if(($this->type == "component" || $this->type == "module") && (strpos($data->element, '_') === false))
        {
            $prefix = array_search($this->type, $this->typeMap);
            $data->element = $prefix . $this->extension;
        }

        // get the #__extensions model and table
        require_once JPATH_ADMINISTRATOR . '/components/com_installer/models/extension.php';

        $model = new \InstallerModel();
        $table = $model->getTable('extension', 'JTable');

        // restrict on same name and type
        $unique = array('name' => $data->name, 'type' => $data->type);

        // does the extension exist?
        if (!$table->load($unique))
        {
            if ($table->save($data->getProperties()))
            {
                if(array_key_exists($this->type, $this->exceptions)){
                    $this->handleExceptions($output, $app, $data, $this->type);
                }

                $output->writeln("<info>Your extension registered as a '{$this->type}', with extension_id: {$table->extension_id}</info>");
            } else {
                $output->writeln("<info>" . $table->getError() . "</info>");
            }
        }
        else $output->writeln("<error>{$this->extension} {$this->type}: That extension already exists.</error>");

        ob_end_clean();
    }

    public function handleExceptions(OutputInterface $output, $app, $data, $extension)
    {
        $data->title = $this->extension;
        $data->published = $data->enabled;

        if($extension == "module"){
            $data->module = $this->extension;
        }

        if($extension == "template")
        {
            $data->template = $this->extension;
            $data->home = 1;
        }

        $exception = $this->exceptions[$this->type];

        foreach($exception['require'] AS $require){
            require_once JPATH_ADMINISTRATOR . $require;
        }

        $model = new $exception['model'];
        $exception_table = $model->getTable($exception['table']['type'], $exception['table']['prefix']);

        if(!$exception_table->save($data->getProperties()))
        {
            $output->writeln("<info>" . $exception_table->getError() . "</info>");
            die();
        }
    }
}