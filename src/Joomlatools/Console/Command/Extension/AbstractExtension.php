<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Extension;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

abstract class AbstractExtension extends Command
{
    /**
     * Extension name
     *
     * @var string
     */
    protected $extension;

    protected $typeMap = array(
        'com_' => 'component',
        'mod_' => 'module',
        'plg_' => 'plugin',
        'pkg_' => 'package',
        'lib_' => 'library',
        'tpl_' => 'template',
        'lng_' => 'language'
    );

    protected $exceptions = array(
        'module' => array(
            'require' => array(
                'model' => '/administrator/components/com_modules/models/module.php'
            ),
            'model' => '\\ModulesModelModule',
            'table' => array(
                'type' => 'module',
                'prefix' => 'JTable'
            ),
        ),
        'template' => array(
            'require' => array(
                'model' => '/administrator/components/com_templates/models/style.php',
                'table' => '/administrator/components/com_templates/tables/style.php'
            ),
            'model' => 'TemplatesModelStyle',
            'table' => array(
                'type' => 'Style',
                'prefix' => 'TemplatesTable'
            ),
        ));

    protected function configure()
    {
        $this->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Alphanumeric site name. Also used in the site URL with .dev domain'
            )->addArgument(
                'extension',
                InputArgument::REQUIRED,
                ''
            )->addOption(
                'www',
                null,
                InputOption::VALUE_REQUIRED,
                "Web server root",
                '/var/www'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site       = $input->getArgument('site');
        $this->www        = $input->getOption('www');
        $this->target_dir = $this->www.'/'.$this->site;
        $this->extension = $input->getArgument('extension');
    }

    protected function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    protected function toggle($enable = false)
    {
        $app = Bootstrapper::getApplication($this->target_dir);

        $dbo = \JFactory::getDbo();
        $query = \JFactory::getDbo()->getQuery(true)
            ->select('extension_id')
            ->from('#__extensions')
            ->where($dbo->quoteName('name') ." = " . $dbo->quote($this->extension));

        $dbo->setQuery($query);
        $extension = $dbo->loadResult('extension_id');

        require_once JPATH_ADMINISTRATOR.'/components/com_installer/models/manage.php';

        $manage = new \InstallerModelManage();
        $manage->publish($extension, (int) $enable);

        $messages = $app->getMessageQueue();

        if (is_array($messages) && count($messages))
        {
            foreach ($messages as $msg)
            {
                if (isset($msg['type']) && isset($msg['message'])) {
                    throw new \RuntimeException(sprintf('Extension %s: %s', $this->extension, $msg['message']));
                }
            }
        }
    }
}