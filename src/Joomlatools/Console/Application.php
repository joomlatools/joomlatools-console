<?php
namespace Joomlatools\Console;

use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;

class Application extends \Symfony\Component\Console\Application
{
    /**
     * joomla-console version
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Application name
     *
     * @var string
     */
    const NAME = 'Joomla Console tools';

    /**
     * The path to the plugin directory
     * 
     * @var string
     */
    protected $_plugin_path;

    /**
     * Reference to the Output\ConsoleOutput object
     */
    protected $_output;

    /**
     * Reference to the Input\ArgvInput object
     */
    protected $_input;

    /**
     * List of installed plugins
     */
    protected $_plugins;

    /**
     * @inherits
     *
     * @param string $name
     * @param string $version
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct(self::NAME, self::VERSION);
        
        $this->_plugin_path = realpath(dirname(__FILE__) . '/../../../plugins/');
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return int 0 if everything went fine, or an error code
     *
     * @throws \Exception When doRun returns Exception
     */
    public function run(Input\InputInterface $input = null, Output\OutputInterface $output = null)
    {
        if (null === $input) {
            $this->_input = new Input\ArgvInput();
        }

        if (null === $output) {
            $this->_output = new Output\ConsoleOutput();
        }

        $this->configureIO($this->_input, $this->_output);

        $autoloader = $this->_plugin_path . '/vendor/autoload.php';

        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        $name = $this->getCommandName($this->_input);

        if (strpos($name, ':') !== false)
        {
            list($group, $command) = explode(':', $name);
            $className = 'Joomlatools\Console\Command\\'. ucfirst($group) . ucfirst($command);
        }
        else $className = 'Joomlatools\Console\Command\\' . $name;

        if (class_exists($className) && !$this->has($name))
        {
            $command = new $className();

            if ($command instanceof \Symfony\Component\Console\Command\Command) {
                $this->add($command);
            }
        }

        parent::run($this->_input, $this->_output);
    }

    /**
     * Get the plugin path
     *
     * @return string Path to the plugins directory
     */
    public function getPluginPath()
    {
        return $this->_plugin_path;
    }


    /**
     * Gets the default commands that should always be available.
     *
     * @return Command[] An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands = array_merge($commands, array(
            new Command\Symlink(),
            new Command\SiteCreate(),
            new Command\SiteDelete(),
            new Command\SiteToken(),
            new Command\ExtensionSymlink(),
            new Command\ExtensionInstall(),
            new Command\ExtensionInstallFile(),
            new Command\PluginList(),
            new Command\PluginInstall(),
            new Command\PluginUninstall(),
            new Command\Versions(),
        ));

        return $commands;
    }

    /**
     * Get the list of installed plugin packages.
     *
     * @return array Array of package names as key and their version as value
     */
    public function getPlugins()
    {
        if (!$this->_plugins) {

            $manifest = $this->_plugin_path . '/composer.json';

            if (!file_exists($manifest)) {
                return array();
            }

            $contents = file_get_contents($manifest);

            if ($contents === false) {
                return array();
            }

            $data = json_decode($contents);

            if (!isset($data->require)) {
                return array();
            }

            $this->_plugins = array();
            foreach ($data->require as $package => $version) {
                $this->_plugins[$package] = $version;
            }
        }

        return $this->_plugins;
    }
}