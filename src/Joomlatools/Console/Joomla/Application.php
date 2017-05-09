<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

use \JApplicationCli as JApplicationCli;
use \JDispatcher as JDispatcher;
use \JFactory as JFactory;
use \JInstaller as JInstaller;
use \JMenu as JMenu;
use \JPluginHelper as JPluginHelper;
use \JSession as JSession;

/**
 * Application extending Joomla CLI class.
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class Application extends JApplicationCli
{
    protected $_clientId     = Bootstrapper::CLI;
    protected $_application  = null;
    protected $_messageQueue = array();
    protected $_options      = array();

    /**
     * Class constructor.
     *
     * @param   array  $options     An array of configuration settings.
     * @param   mixed  $input       An optional argument to provide dependency injection for the application's
     *                              input object.
     * @param   mixed  $config      An optional argument to provide dependency injection for the application's
     *                              config object.
     * @param   mixed  $dispatcher  An optional argument to provide dependency injection for the application's
     *                              event dispatcher.
     * @return  void
     *
     * @see JApplicationCli
     */
    public function __construct($options = array(), JInputCli $input = null, JRegistry $config = null, JDispatcher $dispatcher = null)
    {
        $this->_options = $options;

        parent::__construct($input, $config, $dispatcher);

        if (isset($this->_options['client_id'])) {
            $this->_clientId = $this->_options['client_id'];
        }

        $this->_initialize();
    }

    /**
     * Initialise the application.
     *
     * Loads the necessary Joomla libraries to make sure
     * the Joomla application can run and sets up the JFactory properties.
     *
     * @param   array  $options  An optional associative array of configuration settings.
     * @return  void
     */
    protected function _initialize()
    {
        // Load dependencies
        jimport('joomla.application.component.helper');
        jimport('joomla.application.menu');

        jimport('joomla.environment.uri');

        jimport('joomla.event.dispatcher');

        jimport('joomla.utilities.utility');
        jimport('joomla.utilities.arrayhelper');

        jimport('joomla.application.module.helper');

        // Tell JFactory where to find the current application object
        JFactory::$application = $this;

        // Start a new session and tell JFactory where to find it if we're on Joomla 3
        if(version_compare(JVERSION, '3.0.0', '>=')) {
            JFactory::$session = $this->_startSession();
        }

        // Load plugins
        JPluginHelper::importPlugin('system');

        // Load required languages
        $lang = JFactory::getLanguage();
        $lang->load('lib_joomla', JPATH_ADMINISTRATOR, null, true);
        $lang->load('com_installer', JPATH_ADMINISTRATOR, null, true);

        // Instiantiate the Joomla application object if we
        // need either admin or site
        $name = $this->getName();
        if (in_array($name, array('administrator', 'site'))) {
            $this->_application = \JApplicationCms::getInstance($name);
        }
    }

    /**
     * Authenticates the Joomla user.
     *
     * This method will load the default user object and change its guest status to logged in.
     * It will then simply copy all the properties defined by key in the $credentials argument
     * onto this JUser object, allowing you to completely overwrite the user information.
     *
     * @param array $credentials    Associative array containing user object properties.
     *
     * @return void
     */
    public function authenticate($credentials)
    {
        $user = JFactory::getUser();
        $user->guest = 0;

        foreach($credentials as $key => $value) {
            $user->$key = $value;
        }

        // Push the JUser object into the session otherwise getUser() always returns a new instance of JUser.
        JFactory::getSession()->set('user', $user);
    }

    /**
     * Checks if this Joomla installation has a certain element installed.
     *
     * @param string $element   The name of the element
     * @param string $type      The type of extension
     *
     * @return bool
     */
    public function hasExtension($element, $type = 'component')
    {
        $db = JFactory::getDbo();
        $sql = "SELECT `extension_id`, `state` FROM `#__extensions`"
            ." WHERE `element` = ".$db->quote($element)." AND `type` = ".$db->quote($type);

        $extension = $db->setQuery($sql)->loadObject();

        return ($extension && $extension->state != -1);
    }

    /**
     * Installs an extension from the given path.
     *
     * @param string $path Path to the extracted installation package.
     *
     * @return bool
     */
    public function install($path)
    {
        $installer = $this->getInstaller();

        return $installer->install($path);
    }

    /**
     * Updates an existing extension from the given path.
     *
     * @param string $path Path to the extracted installation package.
     *
     * @return bool
     */
    public function update($path)
    {
        $installer = $this->getInstaller();

        return $installer->update($path);
    }

    /**
     * Retrieves value from the Joomla configuration.
     *
     * @param string $varname   Name of the configuration property
     * @param mixed  $default   Default value
     *
     * @return mixed
     */
    public function getCfg($varname, $default = null)
    {
        return JFactory::getConfig()->get($varname, $default);
    }

    /**
     * Enqueue flash message.
     *
     * @param string $msg   The message
     * @param string $type  Type of message (can be message/notice/error)
     *
     * @return void
     */
    public function enqueueMessage($msg, $type = 'message')
    {
        $this->_messageQueue[] = array('message' => $msg, 'type' => strtolower($type));
    }

    /**
     * Return all currently enqueued flash messages.
     *
     * @return array
     */
    public function getMessageQueue()
    {
        return $this->_messageQueue;
    }

    /**
     * Get the JInstaller object.
     *
     * @return JInstaller
     */
    public function getInstaller()
    {
        // @TODO keep one instance available per install package
        // instead of instantiating a new object each time.
        // Re-using the same instance for multiple installations will fail.
        return new JInstaller();
    }

    public function getPath()
    {
        return JPATH_ROOT;
    }

    /**
     * Get the current template name.
     * Always return 'system' as template.
     *
     * @return string
     */
    public function getTemplate()
    {
        return 'system';
    }

    /**
     * Gets the client id of the current running application.
     *
     * @return  integer  A client identifier.
     */
    public function getClientId()
    {
        return $this->_clientId;
    }

    /**
     * Get the current application name.
     *
     * @return string
     */
    public function getName()
    {
        $name = '';

        switch ($this->_clientId)
        {
            case Bootstrapper::SITE:
                $name = 'site';
                break;
            case Bootstrapper::ADMIN:
                $name = 'administrator';
                break;
            default:
                $name = 'cli';
                break;
        }

        return $name;
    }

    /**
     * Checks if interface is site or not.
     *
     * @return  bool
     */
    public function isSite()
    {
        return $this->_clientId == Bootstrapper::SITE;
    }

    /**
     * Checks if interface is admin or not.
     *
     * @return  bool
     */
    public function isAdmin()
    {
        return $this->_clientId == Bootstrapper::ADMIN;
    }

    /**
     * @param $identifier
     * @return bool
     */
    public function isClient($identifier)
    {
        return $this->getName() === $identifier;
    }

    /**
     * Determine if we are using a secure (SSL) connection.
     *
     * @return  boolean  True if using SSL, false if not.
     */
    public function isSSLConnection()
    {
        return false;
    }

    /**
     * Stub for flushAssets() method.
     */
    public function flushAssets()
    {
    }

    /**
     * Return a reference to the JRouter object.
     *
     * @param   string  $name     The name of the application.
     * @param   array   $options  An optional associative array of configuration settings.
     *
     * @return  JRouter
     */
    public static function getRouter($name = null, array $options = array())
    {
        if (!isset($name))
        {
            $app  = \JFactory::getApplication();
            $name = $app->getName();
        }

        try
        {
            $router = \JRouter::getInstance($name, $options);
        }
        catch (\Exception $e)
        {
            return null;
        }

        return $router;
    }

    /**
     * Method to load a PHP configuration class file based on convention and return the instantiated data object.  You
     * will extend this method in child classes to provide configuration data from whatever data source is relevant
     * for your specific application.
     * Additionally injects the root_user into the configuration.
     *
     * @param   string  $file   The path and filename of the configuration file. If not provided, configuration.php
     *                          in JPATH_BASE will be used.
     * @param   string  $class  The class name to instantiate.
     *
     * @return  mixed   Either an array or object to be loaded into the configuration object.
     *
     * @since   11.1
     */
    protected function fetchConfigurationData($file = '', $class = 'JConfig')
    {
        $config = parent::fetchConfigurationData($file, $class);

        // Inject the root user configuration
        if(isset($this->_options['root_user']))
        {
            $root_user = isset($this->_options['root_user']) ? $this->_options['root_user'] : 'root';

            if (is_array($config)) {
                $config['root_user'] = $root_user;
            }
            elseif (is_object($config)) {
                $config->root_user = $root_user;
            }
        }

        return $config;
    }

    /**
     * Creates a new Joomla session.
     *
     * @return JSession
     */
    protected function _startSession()
    {
        $name     = md5($this->getCfg('secret') . get_class($this));
        $lifetime = $this->getCfg('lifetime') * 60 ;
        $handler  = $this->getCfg('session_handler', 'none');

        $options = array(
            'name' => $name,
            'expire' => $lifetime
        );

        $session = JSession::getInstance($handler, $options);
        $session->initialise($this->input, $this->dispatcher);

        if ($session->getState() == 'expired') {
            $session->restart();
        } else {
            $session->start();
        }

        return $session;
    }

    /**
     * Load an object or array into the application configuration object.
     *
     * @param   mixed  $data  Either an array or object to be loaded into the configuration object.
     *
     * @return  Application  Instance of $this
     */
    public function loadConfiguration($data)
    {
        parent::loadConfiguration($data);

        JFactory::$config = $this->config;

        return $this;
    }

    /**
     * Gets a user state.
     *
     * @param   string  $key      The path of the state.
     * @param   mixed   $default  Optional default value, returned if the internal value is null.
     *
     * @return  mixed  The user state or null.
     *
     * @since   11.1
     */
    public function getUserState($key, $default = null)
    {
        $session = JFactory::getSession();
        $registry = $session->get('registry');

        if (!is_null($registry))
        {
            return $registry->get($key, $default);
        }

        return $default;
    }

    /**
     * Sets the value of a user state variable.
     *
     * @param   string  $key    The path of the state.
     * @param   string  $value  The value of the variable.
     *
     * @return  mixed  The previous state, if one existed.
     *
     * @since   11.1
     */
    public function setUserState($key, $value)
    {
        $session = JFactory::getSession();
        $registry = $session->get('registry');

        if (!is_null($registry))
        {
            return $registry->set($key, $value);
        }

        return null;
    }

    /**
     * Gets the value of a user state variable.
     *
     * @param   string  $key      The key of the user state variable.
     * @param   string  $request  The name of the variable passed in a request.
     * @param   string  $default  The default value for the variable if not found. Optional.
     * @param   string  $type     Filter for the variable, for valid values see {@link JFilterInput::clean()}. Optional.
     *
     * @return  The request user state.
     *
     * @since   11.1
     */
    public function getUserStateFromRequest($key, $request, $default = null, $type = 'none')
    {
        $cur_state = $this->getUserState($key, $default);
        $new_state = \JRequest::getVar($request, null, 'default', $type);

        // Save the new value only if it was set in this request.
        if ($new_state !== null)
        {
            $this->setUserState($key, $new_state);
        }
        else
        {
            $new_state = $cur_state;
        }

        return $new_state;
    }
    
    /**
     * Just a stub to catch anything that calls $app->redirect(), expecting us to be JApplication,
     * rather than JApplicationCLI, such as installer code run via extension:install, so it doesn't
     * drop dead from a fatal PHP error.
     * 
     * @param   string   $url    does nothing
     * @param   boolean  $moved  does nothing
     * 
     * @return  void
     */
    public function redirect($url, $moved = false)
    {
		/**
		 * Throw an exception, to short circuit whatever code called us, as the J! redirect()
		 * would usually close() and go no further, so we don't want to just return.
		 * We can then catch this exception in (for instance) ExtensionInstallFile, and
		 * go about our business.
		 */
    	throw new \RuntimeException(sprintf('Application tried to redirect to %s', $url));
    }

    /**
     * Returns the application JMenu object.
     *
     * @param   string  $name     The name of the application/client.
     * @param   array   $options  An optional associative array of configuration settings.
     *
     * @return  JMenu
     */
    public function getMenu($name = null, $options = array())
    {
        if (!isset($name))
        {
            $app = JFactory::getApplication();
            $name = $app->getName();
        }

        try
        {
            if (!class_exists('JMenu' . ucfirst($name))) {
                jimport('cms.menu.'.strtolower($name));
            }

            $menu = JMenu::getInstance($name, $options);
        }
        catch (Exception $e) {
            return null;
        }

        return $menu;
    }

    /**
     * Forward m
     *
     * @param $method
     * @param $args
     * @return mixed
     * @throws Exception
     */
    public function __call($method, $args)
    {
        if (!method_exists($this, $method) && is_object($this->_application)) {
            return call_user_func_array(array($this->_application, $method), $args);
        }

        return parent::__call($method, $args);
    }
}