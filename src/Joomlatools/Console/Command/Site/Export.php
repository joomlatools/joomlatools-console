<?php
/**
 * Joomlatools Console backup plugin - https://github.com/joomlatools/joomlatools-console-backup
 *
 * @copyright	Copyright (C) 2011 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-console-backup for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Database\AbstractDatabase;

/**
 * Backup plugin class.
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Console
 */
class Export extends AbstractDatabase
{
    protected function configure()
    {
        parent::configure();

        $this->setName('site:export')
            ->addOption(
                'folder',
                null,
                InputOption::VALUE_REQUIRED,
                "Target folder where the backup should be stored. Defaults to site folder",
                null
            )
            ->addOption(
                'filename',
                null,
                InputOption::VALUE_REQUIRED,
                "File name for the backup. Defaults to sitename_date.format",
                null
            )
            ->addOption(
                'include-database',
                null,
                InputOption::VALUE_NEGATABLE,
                'Includes the database contents in the backup',
                true
            )
            ->setDescription('Export site files and database');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check();

        $dbPath = $this->target_dir.'/database.sql';

        if ($input->getOption('include-database')) {
            $this->_backupDatabase($dbPath);
        }

        $this->backupFiles($input, $output); 

        if ($input->getOption('include-database') && \is_file($dbPath)) {
            \unlink($dbPath);
        }

        return 0;
    }

    public function backupFiles(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getOption('folder') ?? $this->target_dir;
        $path .= '/'.($input->getOption('filename') ?? $this->site.'_export_'.date('Y-m-d').'.tar.gz');

        if (\is_file($path)) {
            \unlink($path);
        }

        exec(sprintf("cd %s && tar -czvf %s *", $this->target_dir, $path));
    }

    public function check()
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('The site %s does not exist', $this->site));
        }
    }
}