<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

/**
 * Joomlatools extensions abort on Joomla 5/6 unless the core Backward
 * Compatibility plugin is enabled. Fresh sites ship it disabled.
 */
class Compat
{
    /**
     * Enable the plugin for this site when the Joomla version needs it.
     *
     * @param string $siteDir Absolute path to the Joomla root
     * @return string|null Plugin label if it was just enabled, null if nothing to do
     */
    public static function enable($siteDir)
    {
        $plugin = static::pluginFor($siteDir);

        if ($plugin === null) {
            return null;
        }

        $configFile = rtrim($siteDir, '/') . '/configuration.php';

        if (!is_file($configFile)) {
            return null;
        }

        if (!class_exists('JConfig', false)) {
            require $configFile;
        }

        $config = new \JConfig();
        $host   = $config->host;
        $port   = 3306;

        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host, 2);
            $port = (int) $port;
        }

        $mysqli = @new \mysqli($host, $config->user, $config->password, $config->db, $port);

        if ($mysqli->connect_error) {
            throw new \RuntimeException(sprintf(
                'Could not enable %s: %s',
                $plugin['label'],
                $mysqli->connect_error
            ));
        }

        $element = $mysqli->real_escape_string($plugin['element']);
        $table   = $config->dbprefix . 'extensions';
        $sql     = "UPDATE `{$table}` SET `enabled` = 1 WHERE `type` = 'plugin' AND `folder` = 'behaviour' AND `element` = '{$element}' AND `enabled` = 0";

        if (!$mysqli->query($sql))
        {
            $error = $mysqli->error;
            $mysqli->close();
            throw new \RuntimeException(sprintf('Could not enable %s: %s', $plugin['label'], $error));
        }

        $enabled = $mysqli->affected_rows > 0;
        $mysqli->close();

        return $enabled ? $plugin['label'] : null;
    }

    /**
     * @return array{element: string, label: string, release: string}|null
     */
    public static function pluginFor($siteDir)
    {
        $version = Util::getJoomlaVersion($siteDir);

        if (!$version || version_compare($version->release, '5.0.0', '<')) {
            return null;
        }

        $element = version_compare($version->release, '6.0.0', '>=') ? 'compat6' : 'compat';

        return array(
            'element' => $element,
            'label'   => $element === 'compat6'
                ? 'Behaviour - Backward Compatibility 6'
                : 'Behaviour - Backward Compatibility',
            'release' => $version->release,
        );
    }
}
