<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

use Joomlatools\Console\Command\Extension;
use Joomlatools\Console\Joomla\Util;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Joomlatools Framework custom symlinker
 */
Extension\Symlink::registerSymlinker(function($project, $destination, $name, $projects, OutputInterface $output) {
    if (!is_file($project.'/composer.json')) {
        return false;
    }

    $manifest = json_decode(file_get_contents($project.'/composer.json'));

    if (!isset($manifest->name) || $manifest->name != 'joomlatools/framework') {
        return false;
    }

    // build the folders to symlink into
    $dirs = array(
        Util::buildTargetPath('/media/koowa', $destination),
        Util::buildTargetPath('/libraries/joomlatools-components', $destination)
    );

    foreach ($dirs as $dir)
    {
        if (!is_dir($dir))
        {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(" * creating empty directory `$dir`");
            }

            mkdir($dir, 0755, true);
        }
    }

    /*
     * Special treatment for media files
     */
    $media = array(
        $project.'/code/libraries/joomlatools/component/koowa/resources/assets' => Util::buildTargetPath('/media/koowa/com_koowa', $destination),
        $project.'/code/libraries/joomlatools/library/resources/assets' => Util::buildTargetPath('/media/koowa/framework', $destination),
    );

    foreach ($media as $from => $to)
    {
        if (is_dir($from) && !file_exists($to))
        {
            $from = Extension\Symlink::buildSymlinkPath($from, $to);

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(" * creating link `$to` -> $from");
            }

            `ln -sf $from $to`;
        }
    }

    // Let the default symlinker handle the rest
    return false;
});