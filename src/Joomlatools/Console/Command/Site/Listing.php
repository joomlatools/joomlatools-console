<?php
/**
 * @copyright	Copyright (C) 2007 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Database;
use Joomlatools\Console\Joomla\Util;

class Listing extends Database\AbstractDatabase
{
    protected function configure()
    {
        $this
            ->setName('site:list')
            ->setDescription('List Joomla sites')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'The output format (txt or json)',
                'txt'
            )
            ->addOption(
                'www',
                null,
                InputOption::VALUE_REQUIRED,
                "Web server root",
                '/var/www'
            )
            ->setHelp('List Joomla sites running on this box');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        define('_JEXEC', true);
        define('JPATH_BASE', true);
        define('JPATH_PLATFORM', true);

        $docroot = $input->getOption('www');

        if (!file_exists($docroot)) {
            throw new \RuntimeException(sprintf('Web server root \'%s\' does not exist.', $docroot));
        }

        $dir = new \DirectoryIterator($docroot);
        $sites = array();

        foreach ($dir as $fileinfo)
        {
            if ($fileinfo->isDir() && !$fileinfo->isDot())
            {
                $version = Util::getJoomlaVersion($fileinfo->getPathname());

                if ($version !== false)
                {
                    $sites[] = (object) array(
                        'name'    => $fileinfo->getFilename(),
                        'docroot' => $docroot . '/' . $fileinfo->getFilename() . '/' . ($version->type == 'joomlatools-platform' ? 'web' : ''),
                        'type'    => $version->type == 'joomla-cms-new' ? 'joomla-cms' : $version->type,
                        'version' => $version->release
                    );
                }
            }
        }

        if (!in_array($input->getOption('format'), array('txt', 'json'))) {
            throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $input->getOption('format')));
        }

        switch ($input->getOption('format'))
        {
            case 'json':
                $result = new \stdClass();
                $result->command = $input->getArgument('command');
                $result->data    = $sites;

                $options = (version_compare(phpversion(),'5.4.0') >= 0 ? JSON_PRETTY_PRINT : 0);
                $string  = json_encode($result, $options);
                break;
            case 'txt':
            default:
                $lines = array();
                foreach ($sites as $i => $site) {
                    $lines[] = sprintf("<info>%s. %s</info> (%s %s)", ($i+1), $site->name, $site->type, $site->version);
                }

                $string = implode("\n", $lines);
                break;
        }

        $output->writeln($string);
    }
}
