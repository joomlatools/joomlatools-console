<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

class Util
{
    protected static $_versions = array();

    /**
     * Retrieve the Joomla version.
     * Returns FALSE if unable to find correct version.
     *
     * @param string $base Base path for the Joomla installation
     * @return string|boolean
     */
    public static function getJoomlaVersion($base)
    {
        $key = md5($base);

        if (!isset(self::$_versions[$key]))
        {
            $code = $base . '/libraries/cms/version/version.php';

            if (file_exists($code))
            {
                if (!defined('JPATH_PLATFORM')) {
                    define('JPATH_PLATFORM', $base.'/libraries');
                }

                if (!defined('_JEXEC')) {
                    define('_JEXEC', 1);
                }

                $identifier = uniqid();

                $source = file_get_contents($code);
                $source = preg_replace('/<\?php/', '', $source, 1);
                $source = preg_replace('/class JVersion/i', 'class JVersion' . $identifier, $source);

                eval($source);

                $class   = 'JVersion'.$identifier;
                $version = new $class();

                self::$_versions[$key] = $version->RELEASE.'.'.$version->DEV_LEVEL;
            }
            else self::$_versions[$key] = false;
        }

        return self::$_versions[$key];
    }

    /**
     * Checks if we are dealing with joomlatools/joomla-platform or not
     *
     * @param string $base Base path for the Joomla installation
     * @return boolean
     */
    public static function isPlatform($base)
    {
        $manifest = realpath($base . '/composer.json');

        if (file_exists($manifest))
        {
            $contents = file_get_contents($manifest);
            $package  = json_decode($contents);

            if ($package->name == 'joomlatools/joomla-platform') {
                return true;
            }
        }

        return false;
    }
}