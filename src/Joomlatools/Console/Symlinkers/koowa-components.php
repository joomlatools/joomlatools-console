<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

use Joomlatools\Console\Command\Extension;
use Joomlatools\Console\Joomla\Util;

/**
 * Koowa components custom symlinker
 */
Extension\Symlink::registerSymlinker(function($project, $destination, $name, $projects) {
    if (!is_file($project.'/composer.json')) {
        return false;
    }

    $manifest = json_decode(file_get_contents($project.'/composer.json'));

    if ($manifest->type != 'nooku-component') {
        return false;
    }

    if (!isset($manifest->manifest->name))
    {
        echo "Found koowa component in " . basename($project) . " but failed to find component name in composer.json. Skipping." . PHP_EOL;

        return true;
    }

    $component = 'com_'.$manifest->manifest->name;

    $dirs = array(Util::buildTargetPath('/libraries/koowa/components', $destination), Util::buildTargetPath('/media/koowa', $destination));
    foreach ($dirs as $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    $code_destination = Util::buildTargetPath('/libraries/koowa/components/'.$component, $destination);

    if (!file_exists($code_destination)) {
        `ln -sf $project $code_destination`;
    }

    // Special treatment for media files
    $media = $project.'/resources/assets';
    $target = Util::buildTargetPath('/media/koowa/'.$component, $destination);

    if (is_dir($media) && !file_exists($target)) {
        `ln -sf $media $target`;
    }

    return true;
});