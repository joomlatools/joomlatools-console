<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Symlink;

use Joomlatools\Console\Joomla\Util;

class Iterator extends \RecursiveIteratorIterator
{
    protected $source;
    protected $target;

    /**
     * @param string $source Source dir (usually from an IDE workspace)
     * @param string $target Target dir (usually where a joomla installation resides)
     */
    public function __construct($source, $target)
    {
        $this->source = $source;
        $this->target = $target;

        parent::__construct(new \RecursiveDirectoryIterator($source));
    }

    public function callHasChildren()
    {
        $filename = $this->getFilename();
        if ($filename[0] == '.') {
            return false;
        }

        $source = $this->key();

        $target = str_replace($this->source, '', $source);
        $target = str_replace('/site', '', $target);
        $target = $this->buildTargetPath($target, $this->target);

        if (is_link($target)) {
            unlink($target);
        }

        if (!is_dir($target))
        {
            $this->createLink($source, $target);
            return false;
        }

        return parent::callHasChildren();
    }

    public function createLink($source, $target)
    {
        if (!file_exists($target)) {
            `ln -sf $source $target`;
        }
    }

    public function buildTargetPath($path, $target_directory = '')
    {
        if (!empty($target_directory) && substr($target_directory, -1) == '/') {
            $target_directory = substr($target_directory, 0, -1);
        }

        $path = str_replace($target_directory, '', $path);

        if (Util::isPlatform($target_directory))
        {
            $paths = array(
                '/administrator' => '/app/administrator',
                '/components'    => '/app/site/components',
                '/modules'       => '/app/site/modules',
                '/language'      => '/app/site/language',
                '/media'         => '/web/media',
                '/plugins'       => '/lib/plugins',
                '/libraries'     => '/lib/libraries',
                '/images'        => '/web/images'
            );

            foreach ($paths as $original => $replacement)
            {
                if (substr($path, 0, strlen($original)) == $original)
                {
                    $path = $replacement . substr($path, strlen($original));
                    break;
                }
            }
        }

        return $target_directory.$path;
    }
}