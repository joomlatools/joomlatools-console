<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

class Util
{
    const VERSION_LOCATIONS = [
        '/libraries/cms/version/version.php',
        '/libraries/src/Version.php', // 3.8+
        '/libraries/joomla/version.php'
    ];

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
        return static::getJoomlaVersion($base)->major >= 4;
    }

    public static function executeJ4CliCommand($base, $command): string
    {
        return static::executeCommand(PHP_BINARY . " " . escapeshellarg($base . "/cli/joomla.php") . $command);
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
            self::$_versions[$key] = false;
            foreach (self::VERSION_LOCATIONS as $file)
            {
                $path = $base . $file;

                if (file_exists($path))
                {
                    self::$_versions[$key] = VersionSniffer::fromFile($path);

                    break;
                }
            }
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

        $templatePath = self::getTemplatePath();
        if (!empty($path) || !is_writable($templatePath)) {
            return sys_get_temp_dir() . '/.joomla';
        }

        return $templatePath;
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