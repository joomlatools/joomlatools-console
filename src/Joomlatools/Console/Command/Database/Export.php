<?php
/**
 * Joomlatools Console backup plugin - https://github.com/joomlatools/joomlatools-console-backup
 *
 * @copyright	Copyright (C) 2011 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-console-backup for the canonical source repository
 */

namespace Joomlatools\Console\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


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

        $this->setName('database:export')
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
                'per-table',
                null,
                InputOption::VALUE_NONE,
                "If set, each table will be exported into a separate file",
            )
            ->setDescription('Export the database of a site');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check();

        $folder = $input->getOption('folder') ?? $this->target_dir;

        if ($input->getOption('per-table')) 
        {
            if (!\is_dir($folder)) {
                @mkdir($folder, 0755, true);

                if (!\is_dir($folder)) {
                    throw new \RuntimeException("Folder $folder doesn't exist.");
                }
            }

            $statement = $this->_executePDO('show tables');

            while (($table = $statement->fetchColumn()) !== false) {
                
                $this->_executeMysqldump(sprintf("--skip-dump-date --skip-comments --skip-extended-insert --no-tablespaces %s %s > %s", $this->target_db, $table, $folder.'/'.$table.'.sql'));
            }

        } else {
            $path = $folder.'/'.($input->getOption('filename') ?? $this->site.'_database_'.date('Y-m-d').'.sql');

            $this->_backupDatabase($path);
        }

        return 0;
    }

    public function check()
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('The site %s does not exist', $this->site));
        }

        $result = $this->_executeSQL(sprintf("SHOW DATABASES LIKE \"%s\"", $this->target_db));

        if (empty($result)) {
            throw new \RuntimeException(sprintf('Database %s does not exist', $this->target_db));
        }

    }
}