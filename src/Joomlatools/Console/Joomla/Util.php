<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

class Util
{
    protected static $_versions  = array();

    public static function executeCommand(string $command): string
    {
        exec($command, $output, $code);
        if (count($output) === 0) {
            $outputError = $code;
        } else {
            $outputError = implode(PHP_EOL, $output);
        }

        if ($code !== 0) {
            throw new \RuntimeException(
                "Command failed. The exit code: ".
                $outputError."<br>The last line of output: ".
                $command
            );
        }

        return implode(PHP_EOL, $output);
    }

    public static function isJoomla4($base): bool
    {
         return (bool) \version_compare(static::getJoomlaVersion($base)->release, '4.0.0', '>=');
    }

    public static function executeJ4CliCommand($base, $command): string
    {
        return static::executeCommand("php $base/cli/joomla.php $command");
    }

    /**
     * Retrieve the Joomla version.
     *
     * Returns an object with properties type and release.
     * Returns FALSE if unable to find correct version.
     *
     * @param string $base Base path for the Joomla installation
     * @return stdclass|boolean
     */
    public static function getJoomlaVersion($base)
    {
        $key = md5($base);

        if (!isset(self::$_versions[$key]))
        {
            $canonical = function($version) {
                if (isset($version->RELEASE)) {
                    return 'v' . $version->RELEASE . '.' . $version->DEV_LEVEL;
                }

                // Joomla 3.5 and up uses constants instead of properties in JVersion
                $className = get_class($version);
                if (defined("$className::RELEASE")) {
                    return $version::RELEASE . '.' . $version::DEV_LEVEL;
                }

                //start to provide support for Joomla 4 onwards
                if (defined( "$className::MAJOR_VERSION") && $version::MAJOR_VERSION == '4'){
                    return  $version::MAJOR_VERSION . "." . $version::MINOR_VERSION . "." . $version::PATCH_VERSION . ($version::EXTRA_VERSION ? "." . $version::EXTRA_VERSION : '');
                }

                return 'unknown';
            };

            $files = array(
                'joomla-cms'     => '/libraries/cms/version/version.php',
                'joomla-cms-new' => '/libraries/src/Version.php', // 3.8+
                'joomla-1.5'     => '/libraries/joomla/version.php'
            );

            $code        = false;
            $application = false;
            foreach ($files as $type => $file)
            {
                $path = $base . $file;

                if (file_exists($path))
                {
                    $code        = $path;
                    $application = $type;

                    break;
                }
            }

            if ($code !== false)
            {
                if (!defined('JPATH_PLATFORM')) {
                    define('JPATH_PLATFORM', self::buildTargetPath('/libraries', $base));
                }

                if (!defined('_JEXEC')) {
                    define('_JEXEC', 1);
                }

                $identifier = uniqid();

                $source = file_get_contents($code);
                $source = preg_replace('/<\?php/', '', $source, 1);

                $pattern     = $application == 'joomla-cms-new' ? '/class Version/i' : '/class JVersion/i';
                $replacement = $application == 'joomla-cms-new' ? 'class Version' . $identifier : 'class JVersion' . $identifier;

                $source = preg_replace($pattern, $replacement, $source);

                eval($source);

                $class   = $application == 'joomla-cms-new' ? '\\Joomla\\CMS\\Version'.$identifier : 'JVersion'.$identifier;
                $version = new $class();

                self::$_versions[$key] = (object) array('release' => $canonical($version), 'type' => $application);
            }
            else self::$_versions[$key] = false;
        }
        
        return self::$_versions[$key];
    }

    /**
     * Builds the full path for a given path inside a Joomla project.
     * 
     * @param string $path The original relative path to the file/directory
     * @param string $base The root directory of the Joomla installation
     * @return string Target path
     */
    public static function buildTargetPath($path, $base = '')
    {
        if (!empty($base) && substr($base, -1) == '/') {
            $base = substr($base, 0, -1);
        }

        $path = str_replace($base, '', $path);

        if (substr($path, 0, 1) != '/') {
            $path = '/'.$path;
        }

        return $base.$path;
    }

	/**
	 * Return a writable path
	 *
	 * @return string
	 */
    public static function getWritablePath()
    {
        $path = \Phar::running();

    	if (!empty($path)) {
    		return sys_get_temp_dir() . '/.joomla';
    	}

    	return self::getTemplatePath();
    }

    /**
     * Get template directory path
     *
     * @return string
     */
    public static function getTemplatePath()
    {
        $path = \Phar::running();

    	if (!empty($path)) {
    		return $path . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . '.files';
    	}

    	$root = dirname(dirname(dirname(dirname(__DIR__))));

    	return realpath($root .DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . '.files');
    }
}