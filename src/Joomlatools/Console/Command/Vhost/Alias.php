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
            ->addOption('apache-path',
                null,
                InputOption::VALUE_REQUIRED,
                'The Apache2 path',
                '/etc/apache2'
            )->addOption('nginx-path',
                null,
                InputOption::VALUE_REQUIRED,
                'The Nginx path',
                '/etc/nginx'
            )->addOption('apache-restart',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Apache2',
                null
            )->addOption('nginx-restart',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Nginx',
                null)
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

        $restart = [];

        if ($this->_updateAliases($site, $alias, $delete, $input, 'apache')) {
            $restart[] = 'apache';
        }

        if ($this->_updateAliases($site, $alias, $delete, $input, 'nginx')) {
            $restart[] = 'nginx';
        }

        if ($restart)
        {
            $ignored = array();

            foreach ($restart as $server)
            {
                if ($command = $this->getOption(sprintf('%s-restart', $server))) {
                    `sudo $command`;
                } else {
                    $ignored[] = $server;
                }
            }

            if (Util::isJoomlatoolsBox() && $ignored)
            {
                $arguments = implode(' ', $ignored);

                `box server:restart $arguments`;
            }
        }

        return 0;
    }

    protected function _updateAliases($site, $alias, $delete = false, $input, $application)
    {
        switch ($application)
        {
            case 'nginx':
                $keyword = 'server_name';
                $file    = sprintf('%s/sites-available/1-%s.conf', $input->getOption('apache-path'), $site);
                break;
            case 'apache':
            default:
                $keyword = 'ServerAlias';
                $file    = sprintf('%s/sites-available/1-%s.conf', $input->getOption('nginx-path'), $site);
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