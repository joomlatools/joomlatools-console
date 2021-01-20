<?php
/**
 * @copyright	Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace JoomlatoolsExtended\Console\Command;

use Joomlatools\Console\Command\Site;
use Joomlatools\Console\Joomla\Bootstrapper;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LanguageKey extends Site\AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this->setName('language:key')
             ->setDescription('Generate a language key from the given string')
             ->addArgument('string', InputArgument::REQUIRED, 'The string');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        Bootstrapper::getApplication($this->target_dir);

        $catalogue = \KObjectManager::getInstance()->getObject('com://admin/koowa.translator')->getCatalogue();

        $output->writeln($catalogue->getPrefix().$catalogue->generateKey($input->getArgument('string')));
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