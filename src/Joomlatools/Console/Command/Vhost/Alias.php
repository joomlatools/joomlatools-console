<?php
/**
 * @copyright	Copyright (C) 2007 - 2019 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Vhost;

use Joomlatools\Console\Joomla\Util;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;

class Alias extends AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:alias')
            ->setDescription('Manage aliases for virtual hosts')
            ->addArgument(
                'alias',
                InputArgument::REQUIRED,
                'Virtual host alias'
            )
            ->addOption(
                'delete',
                'D',
                InputOption::VALUE_NONE,
                'Delete the alias if it exists.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $site   = $this->site;
        $alias  = $input->getArgument('alias');
        $delete = $input->getOption('delete');

        $changed = $this->_updateAliases($site, $alias, $delete, 'apache');
        $changed = $this->_updateAliases($site, $alias, $delete, 'nginx') || $changed;

        if ($changed && Util::isJoomlatoolsBox())
        {
            `box server:restart apache nginx`;
        }
    }

    protected function _updateAliases($site, $alias, $delete = false, $application)
    {
        switch ($application)
        {
            case 'nginx':
                $keyword = 'server_name';
                $file    = sprintf('/etc/nginx/sites-available/1-%s.conf', $site);
                break;
            case 'apache':
            default:
                $keyword = 'ServerAlias';
                $file    = sprintf('/etc/apache2/sites-available/1-%s.conf', $site);
                break;
        };

        $lines   = file($file);
        $changed = false;

        foreach ($lines as $i => $line)
        {
            if ($directive = stristr($line, sprintf('%s ', $keyword)))
            {
                $whitespaces = strlen($line) - strlen(ltrim($directive));

                $directive = trim($directive);

                if (substr($directive, -1, 1) == ';')
                {
                    $suffix = ';';
                    $directive = substr($directive, 0, -1);
                }
                else $suffix = '';

                $parts = explode(' ', $directive);

                array_shift($parts);

                $parts = array_filter($parts);
                $key   = array_search($alias, $parts);

                if ($key === false && !$delete)
                {
                    $parts[] = $alias;
                    $changed = true;
                }

                if ($key !== false && $delete)
                {
                    unset($parts[$key]);
                    $changed = true;
                }

                $line = sprintf('%s%s %s%s%s', str_pad(' ', $whitespaces), $keyword, implode(' ', $parts), $suffix, PHP_EOL);
                $lines[$i] = $line;
            }
        }

        if ($changed)
        {
            $tmp  = '/tmp/vhost.alias.tmp';

            `sudo cp $file $file.bak`;

            file_put_contents($tmp, implode('', $lines));

            `sudo tee $file < $tmp`;

            unlink($tmp);

            return true;
        }

        return false;
    }
}