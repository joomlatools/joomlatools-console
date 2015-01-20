<?php
/**
 * @copyright    Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license        Mozilla Public License, version 2.0
 * @link        http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class ExtensionRegister extends SiteAbstract
{
    /**
     * name of extension
     *
     * @var string
     */
    protected $extension = '';
    /**
     * type of extension
     *
     * @var string
     */
    protected $type = '';
    /**
     * File cache
     *
     * @var array
     */
    protected $typeMap = '';
    /**
     * default values
     * @var array
     */
    protected $defaults = '';
    /**
     * extension exceptions
     * @var string
     */
    protected $exceptions = '';

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:register')
            ->setDescription('Register an extension with the with the `#__extensions` table.')
            ->addArgument(
                'extension',
                InputArgument::REQUIRED,
                'The extension name to register'
            )->addArgument('type',
                InputArgument::OPTIONAL,
                'Type of extension being registered. ')
            ->addOption(
                'folder',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specifically for the Plugin typed extension, default "system"'
            )->addOption(
                'enabled',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enabled or not, default is "1"'
            )->addOption(
                'client_id',
                null,
                InputOption::VALUE_OPTIONAL,
                '"0" for Site, "1" for Administrator'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $type = false;

        $this->extension = $input->getArgument('extension');
        $this->defaults = new ExtensionRegisterDefaults();
        $this->typeMap = $this->defaults->typeMap;
        $this->exceptions = $this->defaults->exceptions;

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
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
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

        //need to be sure that a prefix is provided for components and modules
        if(($this->type == "component" || $this->type == "module") && (strpos($data->element, '_') === false))
        {
            $prefix = array_search($this->type, $this->typeMap);
            $data->element = $prefix . $this->extension;
        }

        // get the #__extensions model and table
        require_once $app->getPath() . '/administrator/components/com_installer/models/extension.php';

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
            require_once $app->getPath() . $require;
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