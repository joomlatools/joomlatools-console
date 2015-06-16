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
    protected function configure()
    {
        $this->setName('site:checkin')
             ->setDescription('Checks in all of the database tables of a site')
             ->addOption(
                 'user-column',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'The checkin user column name',
                 'checked_out')
             ->addOption('date-column',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'The checkin date column name',
                 'checked_out_time')
             ->addArgument('site',
                 InputArgument::REQUIRED,
                 'The name of the site to checkin tables from',
                 null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site = $input->getArgument('site');

        // Bootstrap Joomla.
        Bootstrapper::getApplication(sprintf('/var/www/%s', $site));

        $user_column = $input->getOption('user-column');
        $date_column = $input->getOption('date-column');

        $dbo      = \JFactory::getDbo();
        $nullDate = $dbo->getNullDate();

        $tables = $this->_getTables($user_column, $date_column);

        if (empty($tables)) {
            $output->writeln("<comment>Nothing to check in</comment>");
        }

        foreach ($tables as $table)
        {
            $output->writeln("<comment>Checking in {$table} table ...</comment>");

            $query = $dbo->getQuery(true)
                         ->update($dbo->quoteName($table))
                         ->set(sprintf('%s = 0', $user_column))
                         ->set(sprintf('%s = %s', $date_column, $dbo->quote($nullDate)))
                         ->where(sprintf('%s > 0', $user_column));

            $dbo->setQuery($query);

            if ($dbo->execute())
            {
                $affected = $dbo->getAffectedRows();

                $format = 'Table successfully checked in (%s)';

                if ($affected == 1) {
                    $message = sprintf($format, "{$affected} row was checked in");
                }
                else $message = sprintf($format, "{$affected} rows were checked in");

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
     * @param string $user_column The check in user column name.
     * @param string $date_column The check in date column name.
     *
     * @return array An array containing the name of the tables to check in.
     */
    protected function _getTables($user_column, $date_column)
    {
        $prefix = \JFactory::getApplication()->get('dbprefix');
        $dbo    = \JFactory::getDbo();
        $tables = array();

        foreach (\JFactory::getDbo()->getTableList() as $table)
        {
            // Only check in tables with a prefix
            if (stripos($table, $prefix) !== 0) {
                continue;
            }

            $columns = $dbo->getTableColumns($table);

            // Make sure that the table has the check in columns
            if (!isset($columns[$user_column]) || !isset($columns[$date_column])) {
                continue;
            }

            $query = $dbo->getQuery(true)
                         ->select('COUNT(*)')
                         ->from($dbo->quoteName($table))
                         ->where(sprintf('%s > 0', $user_column));

            $dbo->setQuery($query);

            // Only include tables that need to be checked in
            if (!$dbo->execute() || !$dbo->loadResult()) {
                continue;
            }

            $tables[] = $table;
        }

        return $tables;
    }
}