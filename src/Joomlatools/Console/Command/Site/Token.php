<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;
use Joomlatools\Console\Joomla\Util;

class Token extends AbstractSite
{
    protected $extension = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:token')
            ->setDescription('Generate a login token for a user name to be used for JWT authentication')
            ->setHelp('Add the token to your query string such as <comment>?auth_token=TOKEN</comment> and the given user will be automatically logged in')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'User name to generate the token for'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        Bootstrapper::getApplication($this->target_dir);

        require_once Util::buildTargetPath('libraries/joomlatools/library/koowa.php', $this->target_dir);
        require_once Util::buildTargetPath('configuration.php', $this->target_dir);

        \Koowa::getInstance();

        $config = new \JConfig();
        $secret = $config->secret;
        $user   = $input->getArgument('username');

        $token  = \KObjectManager::getInstance()->getObject('http.token')->setSubject($user)->sign($secret);

        $output->writeln($token);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $path = Util::buildTargetPath('libraries/joomlatools/library/koowa.php', $this->target_dir);
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Koowa is not installed on site: %s', $this->site));
        }
    }
}