<?php
/**
 * @copyright    Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license        Mozilla Public License, version 2.0
 * @link        http://github.com/joomlatools/joomla-console for the canonical source repository
 */


namespace Joomlatools\Console\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class ExtensionRegister extends SiteAbstract
{

    protected $extension = '';

    protected $type = '';


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
                'Type of extension being registered. ');
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

        // get the #__extensions model

        ob_start();
        require_once $app->getPath() . '/administrator/components/com_installer/models/extension.php';

        $model = new \InstallerModel();


        // build the record.
        $data = new \JObject;
        $data->name = $this->extension;
        $data->type = $this->type;
        $data->element = $this->extension;

        $table = $model->getTable('extension', 'JTable');

        if ($table->load($data->getProperties())) {
            // already exists.
            $output->writeln("<error>{$this->extension} {$this->type}: That extension already exists.</error>");
            return;
        } else {

            // save the new record

            if ($table->save($data->getProperties())) {
                $id = $table->extension_id;
                // give user some feedback
                $output->writeln("<info>Your extension registered as a '{$this->type}', with extension_id: $id</info>");
            } else {
                $error = $table->getError();
                // give user some feedback
                $output->writeln("<info>" . $error . "</info>");
            }
        }


        ob_end_clean();

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $type = false;
        $typeMap = array('com_' => 'component', 'mod_' => 'module', 'plg_' => 'plugin', 'pkg_' => 'package', 'lib_' => 'library');

        $this->extension = $input->getArgument('extension');

        $forceType = $input->getArgument('type');


         // Try to load the type based on naming convention if we aren't passing a 'type' argument

        if (!$forceType) {

            $prefix = substr($this->extension, 0, 4);
            $type = isset($typeMap[$prefix]) ? $typeMap[$prefix] : false;

        } else if (in_array($forceType, $typeMap)) {
         // only allow ones that exist.
            $type = $forceType;
        }

        if ($type) {
            $this->type = $type;
        } else {
            $output->writeln("<comment>'{$type}' is not allowed as an extension type. Changing to 'component'</comment>");
            $this->type = 'component';
        }

        $this->check($input, $output);
        $this->register($input, $output);
    }

}