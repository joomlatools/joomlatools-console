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
    const VERSION = '2.0.0';

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
     * @inheritdoc
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct(self::NAME, self::VERSION);
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

        $this->_setup();

        parent::run($this->_input, $this->_output);
    }

    /**
     * Get the home directory path
     *
     * @return string Path to the Joomlatools Console home directory
     */
    public function getConsoleHome()
    {
        $home       = getenv('HOME');
        $customHome = getenv('JOOMLATOOLS_CONSOLE_HOME');

        if (!empty($customHome)) {
            $home = $customHome;
        }

        return rtrim($home, '/') . '/.joomlatools/console';
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
            new Command\Database\Install(),
            new Command\Database\Drop(),

            new Command\Extension\Install(),
            new Command\Extension\Register(),
            new Command\Extension\Symlink(),

            new Command\Site\Configure(),
            new Command\Site\Create(),
            new Command\Site\Delete(),
            new Command\Site\Download(),
            new Command\Site\Install(),
            new Command\Site\Listing(),

            new Command\Vhost\Create(),
            new Command\Vhost\Remove(),

            new Command\Versions()
        ));

        return $commands;
    }

    /**
     * Set up environment
     */
    protected function _setup()
    {
        $home = $this->getConsoleHome();

        if (!file_exists($home))
        {
            $result = @mkdir($home, 0775, true);

            if (!$result) {
                $this->_output->writeln(sprintf('<error>Unable to create home directory: %s. Please check write permissions.</error>', $home));
            }
        }
    }
}
