<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Vhost;

use Joomlatools\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;

class Remove extends AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:remove')
            ->setDescription('Removes the Apache2 virtual host')
            ->addOption('folder',
                null,
                InputOption::VALUE_REQUIRED,
                'The Apache2 vhost folder',
                '/etc/apache2/sites-enabled'
            )
            ->addOption('filename',
                null,
                InputOption::VALUE_OPTIONAL,
                'The Apache2 vhost file name',
                null,
            )
            ->addOption('restart-command',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full command for restarting Apache2',
                null
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $file = $this->_getVhostPath($input);

        if (is_file($file))
        {
            $this->_runWithOrWithoutSudo("rm -f $file");

            if ($command = $input->getOption('restart-command')) {
                $this->_runWithOrWithoutSudo($command);
            }
        }

        return 0;
    }

    protected function _getVhostPath($input) 
    {
        $folder = str_replace('[site]', $this->site, $input->getOption('folder'));
        $file = $input->getOption('filename') ?? $input->getArgument('site').'.conf';

        return $folder.'/'.$file;
    }

    protected function _runWithOrWithoutSudo($command) 
    {
        $hasSudo = `which sudo`;

        if ($hasSudo) {
            `sudo $command`;
        } else {
            `$command`;
        }
    }
}