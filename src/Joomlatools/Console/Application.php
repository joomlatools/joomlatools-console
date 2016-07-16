<?php
namespace Joomlatools\Console;

use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;

class Application extends \Symfony\Component\Console\Application
{
    /**
     * joomlatools/console version
     *
     * @var string
     */
    const VERSION = '1.4.6';

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
            $input = new Input\ArgvInput();
        }

        if (null === $output) {
            $output = new Output\ConsoleOutput();
        }

        $this->_input  = $input;
        $this->_output = $output;

        $this->configureIO($this->_input, $this->_output);

        $this->_loadPlugins();

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
            new Command\Cache\Clear(),
            new Command\Cache\ListObjects(),
            new Command\Cache\Purge(),

            new Command\Database\Install(),
            new Command\Database\Drop(),

            new Command\Extension\Disable(),
            new Command\Extension\Enable(),
            new Command\Extension\Install(),
            new Command\Extension\Uninstall(),
            new Command\Extension\InstallFile(),
            new Command\Extension\Register(),
            new Command\Extension\Symlink(),

            new Command\Finder\Index(),
            new Command\Finder\Purge(),

            new Command\Plugin\ListAll(),
            new Command\Plugin\Install(),
            new Command\Plugin\Uninstall(),

            new Command\Site\CheckIn(),
            new Command\Site\Configure(),
            new Command\Site\Create(),
            new Command\Site\Deploy(),
            new Command\Site\Delete(),
            new Command\Site\Download(),
            new Command\Site\Install(),
            new Command\Site\Token(),

            new Command\Vhost\Create(),
            new Command\Vhost\Remove(),

            new Command\Versions()
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

            foreach ($data->require as $package => $version)
            {
                $file = $this->_plugin_path . '/vendor/' . $package . '/composer.json';

                if (file_exists($file))
                {
                    $json     = file_get_contents($file);
                    $manifest = json_decode($json);

                    if (is_null($manifest)) {
                        continue;
                    }

                    if (isset($manifest->type) && $manifest->type == 'joomlatools-console-plugin') {
                        $this->_plugins[$package] = $version;
                    }
                }
            }
        }

        return $this->_plugins;
    }

    /**
     * Loads plugins into the application.
     */
    protected function _loadPlugins()
    {
        $autoloader = $this->_plugin_path . '/vendor/autoload.php';

        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        $plugins = $this->getPlugins();

        $classes = array();
        foreach ($plugins as $package => $version)
        {
            $path        = $this->_plugin_path . '/vendor/' . $package;
            $directories = glob($path.'/*/Console/Command', GLOB_ONLYDIR);

            foreach ($directories as $directory)
            {
                $vendor   = substr($directory, strlen($path) + 1, strlen('/Console/Command') * -1);
                $iterator = new \DirectoryIterator($directory);

                foreach ($iterator as $file)
                {
                    if ($file->getExtension() == 'php') {
                        $classes[] = sprintf('%s\Console\Command\%s', $vendor, $file->getBasename('.php'));
                    }
                }
            }
        }

        foreach ($classes as $class)
        {
            if (class_exists($class))
            {
                $command = new $class();

                if (!$command instanceof \Symfony\Component\Console\Command\Command) {
                    continue;
                }

                $name = $command->getName();

                if(!$this->has($name)) {
                    $this->add($command);
                }
                else $this->_output->writeln("<fg=yellow;options=bold>Notice:</fg=yellow;options=bold> The '$class' command wants to register the '$name' command but it already exists, ignoring.");
            }
        }
    }

    /**
     * Determine the location where joomlatools-console static data files.
     *
     * @return string
     */
    public function getDataDir()
    {
        return dirname(dirname(dirname(__DIR__))) . '/bin/.files';
    }

    /**
     * Determine the location where joomlatools-console can store cache files.
     *
     * @return string Directory path. Ex: "/home/myuser/.cache/joomlatools-console".
     */
    public function getCacheDir()
    {
        $home = strpos(PHP_OS, 'WIN') !== FALSE ? getenv('USERPROFILE') : getenv('HOME');
        $dataDir = "$home/.cache/joomlatools-console";
        foreach (array(dirname($dataDir), $dataDir) as $dir) {
            if (!file_exists($dir))
            {
                mkdir($dir);
            }
            if (!is_dir($dir) || !is_writable($dir))
            {
                throw new \RuntimeException("Failed to find or initialize data dir ($dir)");
            }
        }
        return $dataDir;
    }

}
