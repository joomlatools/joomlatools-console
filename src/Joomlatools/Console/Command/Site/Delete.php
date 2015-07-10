<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Database;
use Joomlatools\Console\Command\Vhost;

class Delete extends Database\AbstractDatabase
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:delete')
            ->setDescription('Delete a site')
            ->addOption(
                'skip-database',
                null,
                InputOption::VALUE_NONE,
                'Leave the database intact'
            )
            ->addOption(
                'skip-vhost',
                null,
                InputOption::VALUE_NONE,
                'Leave the virtual host intact'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);
        $this->deleteFolder($input, $output);
        $this->deleteVirtualHost($input, $output);
        $this->deleteDatabase($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if ((strpos(getcwd(), $this->target_dir) === 0) && (getcwd() !== $this->www)) {
            throw new \RuntimeException('You are currently in the directory you are trying to delete. Aborting');
        }

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('The site %s does not exist!', $this->site));
        }
    }

    public function deleteFolder(InputInterface $input, OutputInterface $output)
    {
        `rm -rf $this->target_dir`;
    }

    public function deleteDatabase(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('skip-database')) {
            return;
        }

        $arguments = array(
            'database:drop',
            'site' => $this->site
        );

        $optionalArgs = array('mysql-login', 'mysql_db_prefix', 'mysql-host', 'mysql-port');
        foreach ($optionalArgs as $optionalArg)
        {
            $value = $input->getOption($optionalArg);
            if (!empty($value)) {
                $arguments['--' . $optionalArg] = $value;
            }
        }

        $command = new Database\Drop();
        $command->run(new ArrayInput($arguments), $output);
    }

    public function deleteVirtualHost(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('skip-vhost')) {
            return;
        }

        $command_input = new ArrayInput(array(
            'vhost:remove',
            'site' => $this->site
        ));

        $command = new Vhost\Remove();
        $command->run($command_input, $output);
    }
}
