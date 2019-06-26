<?php
/**
 * @copyright	Copyright (C) 2007 - 2019 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Vhost;

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

        $file    = sprintf('/etc/apache2/sites-available/1-%s.conf', $site);
        $lines   = file($file);
        $changed = false;

        foreach ($lines as $i => $line)
        {
            if ($directive = stristr($line, 'ServerAlias '))
            {
                $whitespaces = strlen($line) - strlen(ltrim($directive));

                $parts = explode(' ', trim($directive));

                array_shift($parts);

                $parts = array_filter($parts);
                $key   = array_search($alias, $parts);

                if ($key === false)
                {
                    $parts[] = $alias;
                    $changed = true;
                }
                elseif ($delete)
                {
                    unset($parts[$key]);
                    $changed = true;
                }

                $line = sprintf('%sServerAlias %s%s', str_pad(' ', $whitespaces), implode(' ', $parts), PHP_EOL);
                $lines[$i] = $line;
            }
        }

        if ($changed)
        {
            $tmp  = '/tmp/vhost.alias.tmp';

            `sudo cp /etc/apache2/sites-available/1-$site.conf /etc/apache2/sites-available/1-$site.conf.bak`;

            file_put_contents($tmp, implode('', $lines));

            `sudo tee /etc/apache2/sites-available/1-$site.conf < $tmp`;
            `sudo /etc/init.d/apache2 restart > /dev/null 2>&1`;

            unlink($tmp);
        }
    }
}