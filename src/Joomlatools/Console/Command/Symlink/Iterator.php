<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Symlink;

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
        $target = $this->target.$target;

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
}