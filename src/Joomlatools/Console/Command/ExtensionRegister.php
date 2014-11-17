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
                    $output->writeln("<info>Your extension registered with extension_id: $id</info>");
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

        $this->extension = $input->getArgument('extension');
        $type = $input->getArgument('type');

        $allowed = array('component', 'module', 'plugin', 'package', 'library');

        if($type && in_array($type, $allowed)){
            $this->type = $type;
        } else {
            $output->writeln("<comment>'{$type}' is not allowed as an extension type. Changing to 'component'</comment>");
            $this->type = 'component';
        }

        $this->check($input, $output);
        $this->register($input, $output);
    }

}