<?php

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteDelete extends SiteAbstract
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:delete')
            ->setDescription('Delete a site');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->deleteFolder($input, $output);
        $this->deleteVirtualHost($input, $output);
        $this->deleteDatabase($input, $output);
    }

    public function deleteFolder(InputInterface $input, OutputInterface $output)
    {
        `rm -rf $this->target_dir`;
    }

    public function deleteDatabase(InputInterface $input, OutputInterface $output)
    {
        $result = `echo 'DROP DATABASE IF EXISTS $this->target_db' | mysql -uroot -proot`;
        if (!empty($result)) { // MySQL returned an error
            throw new Exception(sprintf('Cannot delete database %s. Error: %s', $this->target_db, $result));
        }
    }

    public function deleteVirtualHost(InputInterface $input, OutputInterface $output)
    {
        `sudo a2dissite 1-$this->site.conf`;
        `sudo rm -f /etc/apache2/sites-available/1-$this->site.conf`;
        `sudo /etc/init.d/apache2 restart > /dev/null 2>&1`;
    }
}