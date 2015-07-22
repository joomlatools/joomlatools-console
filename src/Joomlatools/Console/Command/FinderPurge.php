<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class FinderPurge extends SiteAbstract
{
    private $app = '';

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('finder:purge')
            ->setDescription('Purge all finder indexes')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->app = Bootstrapper::getApplication($this->target_dir);

        // Load Library language
        $lang = \JFactory::getLanguage();

        // Try the finder_cli file in the current language (without allowing the loading of the file in the default language)
        $lang->load('finder_cli', JPATH_SITE, null, false, false)
        // Fallback to the finder_cli file in the default language
        || $lang->load('finder_cli', JPATH_SITE, null, true);

        $this->check($input, $output);
        $this->purgeFinder($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function purgeFinder(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(\JText::_('FINDER_CLI_INDEX_PURGE'));

        require_once $this->app->getPath() . '/administrator/components/com_finder/models/index.php';
        $model = new \FinderModelIndex();

        // Attempt to purge the index.
        $return = $model->purge();

        // If unsuccessful then abort.
        if (!$return)
        {
            $message = \JText::_('FINDER_CLI_INDEX_PURGE_FAILED', $model->getError());

            throw new \RuntimeException($message);
        }

        $output->writeln(\JText::_('FINDER_CLI_INDEX_PURGE_SUCCESS'));
    }

}
