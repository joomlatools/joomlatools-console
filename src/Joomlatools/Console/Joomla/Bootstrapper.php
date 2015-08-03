<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

class Bootstrapper
{
    protected static $_application;

    /**
     * Returns a Joomla application with a root user logged in
     *
     * @param string $base Base path for the Joomla installation
     * @return Application
     */
    public static function getApplication($base)
    {
        $_SERVER['SERVER_PORT'] = 80;

        if (!self::$_application)
        {
            self::bootstrap($base);

            $credentials = array(
                'name'      => 'root',
                'username'  => 'root',
                'groups'    => array(8),
                'email'     => 'root@localhost.home'
            );

            self::$_application = new Application(array('root_user' => 'root'));
            self::$_application->authenticate($credentials);

            // If there are no marks in JProfiler debug plugin performs a division by zero using count($marks)
            \JProfiler::getInstance('Application')->mark('Hello world');
        }

        return self::$_application;
    }

    /**
     * Load the Joomla application files
     *
     * @param $base
     */
    public static function bootstrap($base)
    {
        if (!class_exists('\\JApplicationCli'))
        {
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['HTTP_USER_AGENT'] = 'joomla-console/1.0.0';

            define('_JEXEC', 1);
            define('DS', DIRECTORY_SEPARATOR);

            define('JPATH_BASE', realpath($base));

            require_once JPATH_BASE . '/includes/defines.php';

            require_once JPATH_BASE . '/includes/framework.php';
            require_once JPATH_LIBRARIES . '/import.php';

            require_once JPATH_LIBRARIES . '/cms.php';
        }
    }
} 