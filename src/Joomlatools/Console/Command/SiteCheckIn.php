<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Joomla\Bootstrapper;

class SiteCheckIn extends SiteAbstract
{
    protected $_user_column;

    protected $_date_column;

    protected $_tables;

    protected function configure()
    {
        parent::configure();

        $this->setName('site:checkin')
             ->setDescription('Checks in all of the database tables of a site')
             ->addOption(
                 'user-column',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'The check in user column name',
                 'checked_out')
             ->addOption('date-column',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'The check in date column name',
                 'checked_out_time')
            ->addArgument('tables',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'A list of tables to check in');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site = $input->getArgument('site');

        // Bootstrap Joomla.
        Bootstrapper::getApplication(sprintf('/var/www/%s', $site));

        $this->_user_column = $input->getOption('user-column');
        $this->_date_column = $input->getOption('date-column');

        $dbo      = \JFactory::getDbo();
        $prefix   = \JFactory::getApplication()->get('dbprefix');
        $nullDate = $dbo->getNullDate();

        $tables = $this->_getTables();

        if ($input_tables = $input->getArgument('tables')) {
            $tables = array_intersect($tables, $input_tables);
        }

        if (empty($tables)) {
            $output->writeln("<comment>Nothing to check in</comment>");
        }

        foreach ($tables as $table)
        {
            $output->writeln("<comment>Checking in the {$table} table ...</comment>");

            $table = $prefix . $table;

            $query = $dbo->getQuery(true)
                         ->update($dbo->quoteName($table))
                         ->set(sprintf('%s = 0', $this->_user_column))
                         ->set(sprintf('%s = %s', $this->_date_column, $dbo->quote($nullDate)))
                         ->where(sprintf('%s > 0', $this->_user_column));

            $dbo->setQuery($query);

            if ($dbo->execute())
            {
                $affected = $dbo->getAffectedRows();

                $format = 'Table successfully checked in (%s)';

                if ($affected == 1) {
                    $message = sprintf($format, "{$affected} row was checked in");
                } else {
                    $message = sprintf($format, "{$affected} rows were checked in");
                }

                $output->writeln("<info>{$message}</info>");
            }
            else $output->writeln("<error>There was an error while trying to check in the table</error>");
        }
    }

    /**
     * Check in tables getter.
     *
     * Only tables that need to be checked in will be returned.
     *
     * @return array An array containing the name of the tables to check in.
     */
    protected function _getTables()
    {
        $prefix = \JFactory::getApplication()->get('dbprefix');
        $dbo    = \JFactory::getDbo();
        $tables = array();

        foreach (\JFactory::getDbo()->getTableList() as $table)
        {
            // Only check in tables with a prefix
            if (stripos($table, $prefix) === 0)
            {
                $columns = $dbo->getTableColumns($table);

                // Make sure that the table has the check in columns
                if (!isset($columns[$this->_user_column]) || !isset($columns[$this->_date_column])) {
                    continue;
                }

                // Check the column's types.
                if (stripos($columns[$this->_user_column], 'int') !== 0 || stripos($columns[$this->_date_column], 'date') !== 0) {
                    continue;
                }

                $query = $dbo->getQuery(true)
                             ->select('COUNT(*)')
                             ->from($dbo->quoteName($table))
                             ->where(sprintf('%s > 0', $this->_user_column));

                $dbo->setQuery($query);

                // Only include tables that need to be checked in
                if (!$dbo->execute() || !$dbo->loadResult()) {
                    continue;
                }

                $tables[] = str_replace($prefix, '', $table);
            }
        }

        return $tables;
    }
}