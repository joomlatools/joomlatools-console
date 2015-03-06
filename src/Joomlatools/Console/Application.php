<?php
namespace Joomlatools\Console;

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
     * Load custom plugins into the application.
     */
    function loadPlugins()
    {
        $manifest = $this->_plugin_path . 'composer.json';

        if (!file_exists($manifest)) {
            return;
        }

        $contents = file_get_contents($manifest);

        if ($contents === false) {
            return;
        }

        $data = json_decode($contents);

        if (!isset($data->require)) {
            return;
        }

        $autoloader = $this->_plugin_path . 'vendor/autoload.php';

        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        foreach ($data->require as $package => $version)
        {
            $package_dir = $this->_plugin_path . 'vendor/' . $package . '/Joomlatools/Console/Command/';

            if (file_exists($package_dir))
            {
                $iterator = new DirectoryIterator($package_dir);

                foreach ($iterator as $file)
                {
                    if ($file->getExtension() == 'php')
                    {
                        $class_name = 'Joomlatools\Console\Command\\' . $file->getBasename('.php');

                        if (class_exists($class_name))
                        {
                            $command = new $class_name();

                            // If a command with the current command name was already added, ignore it.
                            if ($command instanceof Command && !$this->has($command->getName())) {
                                $this->add($command);
                            }
                        }
                    }
                }
            }
        }
    }
}