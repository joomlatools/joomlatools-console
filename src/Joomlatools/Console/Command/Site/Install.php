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

class Install extends Database\AbstractDatabase
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:install')
            ->setDescription('Install an existing Joomla codebase. Sets up configuration and installs the database.')
            ->addOption(
                'sample-data',
                null,
                InputOption::VALUE_REQUIRED,
                'Sample data to install (default|blog|brochure|learn|testing)'
            )
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'Overwrite configuration.php if it already exists'
            )
            ->addOption(
                'drop',
                'd',
                InputOption::VALUE_NONE,
                'Drop database if it already exists'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        $this->importdb($input, $output);
        $this->createConfig($input, $output);

        $output->writeln("Your new Joomla site has been configured.");
        $output->writeln("You can login using the following username and password combination: <info>admin</info>/<info>admin</info>.");
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('The site %s does not exist!', $this->site));
        }
    }

    public function importdb(InputInterface $input, OutputInterface $output)
    {
        $arguments = array(
            'site:database:install',
            'site'          => $this->site
        );

        $sample_data = $input->getOption('sample-data');
        if (!empty($sample_data)) {
            $arguments['--sample-data'] = $sample_data;
        }

        if ($input->getOption('drop')) {
            $arguments['--drop'] = true;
        }

        $command = new Database\Install();
        $command->run(new ArrayInput($arguments), $output);
    }

    public function createConfig(InputInterface $input, OutputInterface $output)
    {
        $arguments = array(
            'site:configure',
            'site'          => $this->site
        );

        if ($input->getOption('overwrite')) {
            $arguments['--overwrite'] = true;
        }

        $command = new Configure();
        $command->run(new ArrayInput($arguments), $output);
    }
}
