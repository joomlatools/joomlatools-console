<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class Configurable extends Command
{
    protected $_config = null;


    public function addArgument($name, $mode = null, $description = '', $default = null)
    {
        if ($mode != InputOption::VALUE_NONE) {
            $default = $this->_getConfigOverride($name) ?? $default;
        }

        return parent::addArgument($name, $mode, $description, $default);
    }

    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        if ($mode != InputOption::VALUE_NONE) {
            $default = $this->_getConfigOverride($name) ?? $default;
        }

        return parent::addOption($name, $shortcut, $mode, $description, $default);
    }

    protected function _getConfigOverride($name)
    {
        $override = null;

        if (is_null($this->_config))
        {
            $file = sprintf('%s/.joomlatools/console/config.yaml', trim(`echo ~`));

            if (file_exists($file)) 
            {
                $file_contents = \file_get_contents($file);
                $env = $_ENV;
                
                // Replace longest keys first
                uksort($env, function($a, $b){
                    return strlen($b) - strlen($a);
                });
                
                // Replace environment variables in the file
                foreach ($env as $key => $value) {
                    $file_contents = \str_replace('$'.$key, $value, $file_contents);
                }

                $this->_config = Yaml::parse($file_contents);
            } else {
                $this->_config = false;
            }
        }

        $config = $this->_config;

        if (is_array($config))
        {
            if ($command = $this->getName())
            {
                if (isset($config[$command][$name])) {
                    $override = $config[$command][$name];
                }
            }

            // Look for global settings

            if (is_null($override) && isset($config['globals'][$name])) {
                $override = $config['globals'][$name];
            }
        }

        return $override;
    }
}