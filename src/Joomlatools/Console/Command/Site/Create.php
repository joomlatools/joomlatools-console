<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Database;
use Joomlatools\Console\Command\Vhost;
use Joomlatools\Console\Joomla\Util;

class Create extends Database\AbstractDatabase
{
    /**
     * Clear cache before fetching versions
     *
     * @var bool
     */
    protected $clear_cache = false;

    /**
     * Joomla version to install
     *
     * @var string
     */
    protected $version;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:create')
            ->setDescription('Create a new Joomla site from scratch')
            ->setHelp(<<<EOF
To create a site with the latest Joomla version, run:

    <info>joomla site:create foobar</info>

The newly installed site will be available at <comment>/var/www/foobar</comment> and <comment>foobar.test</comment> after that. You can login into your fresh Joomla installation using these credentials: admin/admin.
By default, the web server root is set to <comment>/var/www</comment>. You can pass <comment>–www=/my/server/path</comment> to commands for custom values.

The console can also install the Joomlatools Platform out of the box by adding the <comment>--repo=platform</comment> flag:

    <info>joomla site:create joomlatools-platform --repo=platform</info>

You can choose the Joomla version or the sample data to be installed. A more elaborate example:

    <info>joomla site:create testsite --release=2.5 --sample-data=blog</info>
EOF
    )
            ->addOption(
                'release',
                null,
                InputOption::VALUE_REQUIRED,
                "Joomla version. Can be a release number (2, 3.2, ..) or branch name. Run `joomla versions` for a full list.\nUse \"none\" for an empty virtual host.",
                'latest'
            )
            ->addOption(
                'sample-data',
                null,
                InputOption::VALUE_REQUIRED,
                'Sample data to install (default|blog|brochure|learn|testing)'
            )
            ->addOption(
                'symlink',
                null,
                InputOption::VALUE_REQUIRED,
                'A comma separated list of directories to symlink from the projects directory. Use \'all\' to symlink every folder.'
            )
            ->addOption(
                'repo',
                null,
                InputOption::VALUE_REQUIRED,
                'Alternative Git repository to use. Also accepts a gzipped tar archive instead of a Git repository. To use joomlatools/platform, use --repo=platform. For Kodekit Platform, use --repo=kodekit-platform.'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Update the list of available tags and branches from the Joomla repository'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            )
            ->addOption(
                'http-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTP port the virtual host should listen to',
                80
            )
            ->addOption(
                'disable-ssl',
                null,
                InputOption::VALUE_NONE,
                'Disable SSL for this site'
            )
            ->addOption(
                'ssl-crt',
                null,
                InputOption::VALUE_REQUIRED,
                'The full path to the signed cerfificate file',
                '/etc/apache2/ssl/server.crt'
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_REQUIRED,
                'The full path to the private cerfificate file',
                '/etc/apache2/ssl/server.key'
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The port on which the server will listen for SSL requests',
                443
            )
            ->addOption(
                'interactive',
                null,
                InputOption::VALUE_NONE,
                'Prompt for configuration details'
            )
            ->addOption(
                'options',
                null,
                InputOption::VALUE_REQUIRED,
                "A YAML file consisting of serialized parameters to override JConfig."
            )
            ->addOption(
                'clone',
                null,
                InputOption::VALUE_OPTIONAL,
                'Clone the Git repository instead of creating a copy in the target directory. Use --clone=shallow for a shallow clone or leave empty.',
                true
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->version = $input->getOption('release');

        $this->check($input, $output);

        $this->download($input, $output);
        $this->addVirtualHost($input, $output);

        if (!file_exists($this->target_dir)) {
            `mkdir -p $this->target_dir`;
        }

        if ($this->version != 'none')
        {
            $arguments = array(
                'site:install',
                'site'   => $this->site,
                '--www'  => $this->www
            );

            $optionalArgs = array('sample-data', 'symlink', 'projects-dir', 'interactive', 'mysql-login', 'mysql_db_prefix', 'mysql-db-prefix', 'mysql-host', 'mysql-port', 'mysql-database', 'options');
            foreach ($optionalArgs as $optionalArg)
            {
                $value = $input->getOption($optionalArg);
                if (!empty($value)) {
                    $arguments['--' . $optionalArg] = $value;
                }
            }

            $command = new Install();
            $command->setApplication($this->getApplication());
            $command->run(new ArrayInput($arguments), $output);
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('A site with name %s already exists', $this->site));
        }
    }

    public function download(InputInterface $input, OutputInterface $output)
    {
        $arguments = array(
            'site:download',
            'site'          => $this->site,
            '--release'     => $input->getOption('release'),
            '--clear-cache' => $input->getOption('clear-cache'),
            '--www'         => $this->www
        );

        if ($input->hasParameterOption('--clone')) {
            $arguments['--clone'] = $input->getOption('clone');
        }

        $repo = $input->getOption('repo');
        if (!empty($repo)) {
            $arguments['--repo'] = $repo;
        }

        $command = new Download();
        $command->run(new ArrayInput($arguments), $output);
    }

    public function addVirtualHost(InputInterface $input, OutputInterface $output)
    {
        $command_input = new ArrayInput(array(
            'vhost:create',
            'site'          => $this->site,
            '--http-port'   => $input->getOption('http-port'),
            '--disable-ssl' => $input->getOption('disable-ssl'),
            '--ssl-crt'     => $input->getOption('ssl-crt'),
            '--ssl-key'     => $input->getOption('ssl-key'),
            '--ssl-port'    => $input->getOption('ssl-port'),
            '--www'         => $input->getOption('www')
        ));

        $command = new Vhost\Create();
        $command->run($command_input, $output);
    }
}
