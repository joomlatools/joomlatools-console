<?php
namespace Joomlatools\Console;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

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
     * Reference to the ConsoleOutput object
     */
    protected $_output;

    /**
     * Reference to the ArgvInput object
     */
    protected $_input;

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
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $input) {
            $this->_input = new ArgvInput();
        }

        if (null === $output) {
            $this->_output = new ConsoleOutput();
        }

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
            new Command\Symlink(),
            new Command\SiteCreate(),
            new Command\SiteDelete(),
            new Command\SiteToken(),
            new Command\ExtensionSymlink(),
            new Command\ExtensionInstall(),
            new Command\ExtensionInstallFile(),
            new Command\PluginInstall(),
            new Command\PluginUninstall(),
            new Command\Versions(),
        ));

        return $commands;
    }

    /**
     * Load custom plugins into the application
     */
    protected function _loadPlugins()
    {
        $manifest = $this->_plugin_path . '/composer.json';

        if (!file_exists($manifest)) {
            return;
        }

        $autoloader = $this->_plugin_path . '/vendor/autoload.php';

        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        $contents = file_get_contents($manifest);

        if ($contents === false) {
            return;
        }

        $data = json_decode($contents);

        if (!isset($data->require)) {
            return;
        }

        foreach ($data->require as $package => $version)
        {
            $package_dir = $this->_plugin_path . '/vendor/' . $package . '/Joomlatools/Console/Command/';

            if (file_exists($package_dir))
            {
                $iterator = new \DirectoryIterator($package_dir);

                foreach ($iterator as $file)
                {
                    if ($file->getExtension() == 'php')
                    {
                        $class_name = 'Joomlatools\Console\Command\\' . $file->getBasename('.php');

                        if (class_exists($class_name))
                        {
                            $command = new $class_name();

                            if ($command instanceof \Symfony\Component\Console\Command\Command)
                            {
                                if (!$this->has($command->getName())) {
                                    $this->add($command);
                                }
                                else $this->_output->writeln('<error>Warning:</error> command "' . $command->getName() . '" in "' . $package . '" already exists, skipping');
                            }
                        }
                    }
                }
            }
        }
    }
}