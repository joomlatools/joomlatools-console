<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Vhost;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;

class Create extends AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:create')
            ->setDescription('Creates a new Apache2 virtual host')
            ->addOption(
                'http-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTP port the virtual host should listen to',
                80
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTPS port the virtual host should listen to',
                443
            )
            ->addOption(
                'apache-template',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom file to use as the Apache vhost configuration. Make sure to include HTTP and SSL directives if you need both.',
                null
            )
            ->addOption('apache-path',
                null,
                InputOption::VALUE_REQUIRED,
                'The Apache2 path',
                '/etc/apache2'
            )->addOption('apache-restart',
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

        $site    = $input->getArgument('site');

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $tmp = '/tmp/vhost.tmp';

        $variables = $this->_getVariables($input);

        $folder = sprintf('%s/sites-available', $input->getOption('apache-path'));

        if (is_dir($folder))
        {
            $template = $this->_getTemplate($input);
            $template = str_replace(array_keys($variables), array_values($variables), $template);

            file_put_contents($tmp, $template);

            $this->_runWithOrWithoutSudo("tee $folder/100-$site.conf < $tmp");

            $link = sprintf('%s/sites-enabled/100-%s.conf', $input->getOption('apache-path'), $site);

            $this->_runWithOrWithoutSudo("ln -fs $folder/100-$site.conf $link");

            if ($command = $input->getOption('apache-restart')) {
                $this->_runWithOrWithoutSudo($command);
            }

            @unlink($tmp);
        }

        return 0;
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

    protected function _getVariables(InputInterface $input)
    {
        $documentroot = $this->target_dir;

        $variables = array(
            '%site%'       => $input->getArgument('site'),
            '%root%'       => $documentroot,
            '%http_port%'  => $input->getOption('http-port'),
            '%ssl_port%'  => $input->getOption('ssl-port'),
        );

        return $variables;
    }

    protected function _getTemplate(InputInterface $input)
    {
        if ($template = $input->getOption('apache-template'))
        {
            if (file_exists($template))
            {
                $file = basename($template);
                $path = dirname($template);
            }
            else throw new \Exception(sprintf('Template file %s does not exist.', $template));
        }
        else
        {
            $path = realpath(__DIR__.'/../../../../../bin/.files/vhosts');

            $file = 'apache.conf';
        }

        $template = file_get_contents(sprintf('%s/%s', $path, $file));

        return $template;
    }
}
