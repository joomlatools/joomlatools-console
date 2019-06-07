<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

class Bootstrapper
{
    const SITE  = 0;
    const ADMIN = 1;
    const CLI   = 2;

    protected static $_application;

    /**
     * Returns a Joomla application with a root user logged in
     *
     * @param string $base Base path for the Joomla installation
     * @param int    $client_id Application client id to spoof. Defaults to admin.
     *
     * @return Application
     */
    public static function getApplication($base, $client_id = self::ADMIN)
    {
        $_SERVER['SERVER_PORT'] = 80;

        if (!self::$_application)
        {
            self::bootstrap($base);

            $options = array(
                'root_user' => 'root',
                'client_id' => $client_id
            );

            self::$_application = new Application($options);

            $credentials = array(
                'name'      => 'root',
                'username'  => 'root',
                'groups'    => array(8),
                'email'     => 'root@localhost.home'
            );

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
            $_SERVER['HTTP_USER_AGENT'] = 'joomlatools-console/' . \Joomlatools\Console\Application::VERSION;

            if (!defined('_JEXEC')) {
                define('_JEXEC', 1);
            }

            if (!defined('DS')) {
                define('DS', DIRECTORY_SEPARATOR);
            }

            if (Util::isPlatform($base))
            {
                define('JPATH_WEB'   , $base.'/web');
                define('JPATH_ROOT'  , $base);
                define('JPATH_BASE'  , JPATH_ROOT . '/app/administrator');
                define('JPATH_CACHE' , JPATH_ROOT . '/cache/site');
                define('JPATH_THEMES', __DIR__.'/templates');

                require_once JPATH_ROOT . '/app/defines.php';
                require_once JPATH_ROOT . '/app/bootstrap.php';
            }
            else
            {
                define('JPATH_BASE', realpath($base));

                require_once JPATH_BASE . '/includes/defines.php';
                require_once JPATH_BASE . '/includes/framework.php';
            }
        }
    }
} 