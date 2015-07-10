<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Database;
use Joomlatools\Console\Command\Vhost;

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
            ->addOption(
                'joomla',
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
                'A comma separated list of folders to symlink from projects folder'
            )
            ->addOption(
                'repo',
                null,
                InputOption::VALUE_OPTIONAL,
                'Alternative Git repository to clone'
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
                'disable-ssl',
                null,
                InputOption::VALUE_NONE,
                'Disable SSL for this site'
            )
            ->addOption(
                'ssl-crt',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the signed cerfificate file',
                '/etc/apache2/ssl/server.crt'
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the private cerfificate file',
                '/etc/apache2/ssl/server.key'
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'The port on which the server will listen for SSL requests',
                '443'
            )
            ->addOption(
                'interactive',
                null,
                InputOption::VALUE_NONE,
                'Prompt for configuration details'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->version = $input->getOption('joomla');

        $this->check($input, $output);

        `mkdir -p $this->target_dir`;

        $this->download($input, $output);
        $this->addVirtualHost($input, $output);

        if ($this->version != 'none')
        {
            $arguments = array(
                'site:install',
                'site'           => $this->site
            );

            $optionalArgs = array('sample-data', 'symlink', 'projects-dir', 'interactive', 'mysql-login', 'mysql_db_prefix', 'mysql-host', 'mysql-port');
            foreach ($optionalArgs as $optionalArg)
            {
                $value = $input->getOption($optionalArg);
                if (!empty($value)) {
                    $arguments['--' . $optionalArg] = $value;
                }
            }

            $command = new Install();
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
            '--joomla'      => $input->getOption('joomla'),
            '--clear-cache' => $input->getOption('clear-cache')
        );

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
            '--disable-ssl' => $input->getOption('disable-ssl'),
            '--ssl-crt'     => $input->getOption('ssl-crt'),
            '--ssl-key'     => $input->getOption('ssl-key'),
            '--ssl-port'    => $input->getOption('ssl-port')
        ));

        $command = new Vhost\Create();
        $command->run($command_input, $output);
    }
}
