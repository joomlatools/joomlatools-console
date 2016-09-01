<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Database;

class Listing extends Database\AbstractDatabase
{
    protected function configure()
    {
        $this
            ->setName('site:list')
            ->setDescription('List Joomla sites')
            ->setHelp('List Joomla sites running on this box');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        define('_JEXEC', true);
        define('JPATH_BASE', true);
        define('JPATH_PLATFORM', true);

        $dir = new \DirectoryIterator('/var/www');
        $sites = array();

        $canonical = function($version) {
            if (isset($version->RELEASE)) {
                return 'v' . $version->RELEASE . '.' . $version->DEV_LEVEL;
            }

            // Joomla 3.5 and up uses constants instead of properties in JVersion
            $className = get_class($version);
            if (defined("$className::RELEASE")) {
                return 'v'. $version::RELEASE . '.' . $version::DEV_LEVEL;
            }

            return 'unknown';
        };

        foreach ($dir as $fileinfo)
        {
            $code = $application = null;

            if ($fileinfo->isDir() && !$fileinfo->isDot())
            {
                $files = array(
                    'joomla-cms'           => $fileinfo->getPathname() . '/libraries/cms/version/version.php',
                    'joomlatools-platform' => $fileinfo->getPathname() . '/lib/libraries/cms/version/version.php',
                    'joomla-1.5'           => $fileinfo->getPathname() . '/libraries/joomla/version.php'
                );

                foreach ($files as $type => $file)
                {
                    if (file_exists($file))
                    {
                        $code        = $file;
                        $application = $type;

                        break;
                    }
                }

                if (!is_null($code) && file_exists($code))
                {
                    $identifier = uniqid();

                    $source = file_get_contents($code);
                    $source = preg_replace('/<\?php/', '', $source, 1);
                    $source = preg_replace('/class JVersion/i', 'class JVersion' . $identifier, $source);

                    eval($source);

                    $class   = 'JVersion'.$identifier;
                    $version = new $class();

                    $sites[] = (object) array(
                        'name'    => $fileinfo->getFilename(),
                        'docroot' => $fileinfo->getFilename() . '/' . ($application == 'joomlatools-platform' ? 'web' : ''),
                        'type'    => $application,
                        'version' => $canonical($version)
                    );
                }
            }
        }

        $output->write("\n");
        $output->writeln("<comment>Sites Running On This Box:</comment>");
        $i = 1;
        foreach ($sites as $site):
            $output->write("\n");
            $output->write(sprintf("<info>%s. %s</info> (%s %s)", $i, $site->name, $site->type, $site->version));
            $i++;
        endforeach;
        $output->write("\n");
    }
}
