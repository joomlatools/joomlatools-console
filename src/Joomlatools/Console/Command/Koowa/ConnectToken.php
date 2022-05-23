<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Koowa;

use Joomlatools\Console\Command\Site;
use Joomlatools\Console\Joomla\Bootstrapper;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConnectToken extends Site\AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this->setName('koowa:connect:token')
             ->setDescription('Generate a JWT token for Joomlatools Connect');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        Bootstrapper::getApplication($this->target_dir);

        $output->writeln(\PlgKoowaConnect::generateToken());

        return 0;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        if (!is_dir($this->target_dir.'/libraries/joomlatools/')) {
            throw new \RuntimeException(sprintf('Koowa is not installed on site: %s', $this->site));
        }
    }
}