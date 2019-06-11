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
 * Koowa components custom symlinker
 */
Extension\Symlink::registerSymlinker(function($project, $destination, $name, $projects, OutputInterface $output) {
    if (!is_file($project.'/composer.json')) {
        return false;
    }

    $manifest = json_decode(file_get_contents($project.'/composer.json'));

    if (!isset($manifest->extra) || !isset($manifest->extra->{'joomlatools-component'})) {
        return false;
    }

    $component   = $manifest->extra->{'joomlatools-component'};
    $code_folder = Util::buildTargetPath('/libraries/joomlatools-components', $destination);

    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
        $output->writeln("Symlinking `$component` into `$destination`");
    }

    $dirs = array(
        $code_folder,
        Util::buildTargetPath('/media/koowa', $destination)
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

    $code_destination = $code_folder.'/'.$component;

    if (!file_exists($code_destination))
    {
        $project = Extension\Symlink::buildSymlinkPath($project, $code_destination);

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(" * creating link `$code_destination` -> $project");
        }

        `ln -sf $project $code_destination`;
    }

    // Media folder always has com_ prefix
    if (substr($component, 0, 4) !== 'com_') {
        $component = 'com_'.$component;
    }

    // Special treatment for media files
    $media = $project.'/resources/assets';
    $target = Util::buildTargetPath('/media/koowa/'.$component, $destination);

    if (is_dir($media) && !file_exists($target))
    {
        $media = Extension\Symlink::buildSymlinkPath($media, $target);

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(" * creating link `$target` -> $media");
        }

        `ln -sf $media $target`;
    }

    return true;
});