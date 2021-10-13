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
use Joomlatools\Console\Joomla\Util;

class Create extends AbstractSite
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:create')
            ->setDescription('Creates a new Apache2 and/or Nginx virtual host')
            ->addOption(
                'http-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTP port the virtual host should listen to',
                80
            )
            ->addOption(
                'disable-ssl',
                null,
                InputOption::VALUE_NONE,
                'Disable SSL for this site'
            )
            ->addOption(
                'ssl-crt',
                null,
                InputOption::VALUE_REQUIRED,
                'The full path to the signed cerfificate file',
                '/etc/apache2/ssl/server.crt'
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_REQUIRED,
                'The full path to the private cerfificate file',
                '/etc/apache2/ssl/server.key'
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The port on which the server will listen for SSL requests',
                443
            )
            ->addOption(
                'php-fpm-address',
                null,
                InputOption::VALUE_REQUIRED,
                'PHP-FPM address or path to Unix socket file, set as value for fastcgi_pass in Nginx config',
                'unix:/opt/php/php-fpm.sock'
            )
            ->addOption(
                'apache-template',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom file to use as the Apache vhost configuration. Make sure to include HTTP and SSL directives if you need both.',
                null
            )
            ->addOption(
                'nginx-template',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom file to use as the Nginx vhost configuration. Make sure to include HTTP and SSL directives if you need both.',
                null
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

        $site    = $input->getArgument('site');
        $restart = array();

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $tmp = '/tmp/vhost.tmp';

        $variables = $this->_getVariables($input);

        $folder = sprintf('%s/sites-available', $input->getOption('apache-path'));

        if (is_dir($folder))
        {
            $template = $this->_getTemplate($input, 'apache');
            $template = str_replace(array_keys($variables), array_values($variables), $template);

            file_put_contents($tmp, $template);

            `sudo tee $folder/1-$site.conf < $tmp`;

            $link = sprintf('%s/sites-enabled/1-%s.conf', $input->getOption('apache-path'), $site);

            `sudo ln -fs $folder/1-$site.conf $link`;

            $restart[] = 'apache';

            @unlink($tmp);
        }

        $folder = sprintf('%s/sites-available', $input->getOption('nginx-path'));

        if (is_dir($folder))
        {
            if (Util::isJoomlatoolsBox() && $variables['%http_port%'] == 80) {
                $variables['%http_port%'] = 81;
            }

            $template = $this->_getTemplate($input, 'nginx');

            if (!$input->getOption('disable-ssl') && Util::isJoomlatoolsBox() && $variables['%ssl_port%'] == 443) {
                $variables['%ssl_port%'] = 444;
            }

            $vhost = str_replace(array_keys($variables), array_values($variables), $template);

            file_put_contents($tmp, $vhost);

            `sudo tee $folder/1-$site.conf < $tmp`;

            $link = sprintf('%s/sites-enabled/1-%s.conf', $input->getOption('nginx-path'), $site);

            `sudo ln -fs $folder/1-$site.conf $link`;

            $restart[] = 'nginx';

            @unlink($tmp);
        }

        if ($restart)
        {
            $ignored = array();

            foreach ($restart as $server)
            {
                if ($command = $input->getOption(sprintf('%s-restart', $server))) {
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

    protected function _getVariables(InputInterface $input)
    {
        $documentroot = $this->target_dir;

        $variables = array(
            '%site%'       => $input->getArgument('site'),
            '%root%'       => $documentroot,
            '%http_port%'  => $input->getOption('http-port'),
            '%php_fpm%'    => $input->getOption('php-fpm-address')
        );

        if (!$input->getOption('disable-ssl'))
        {
            $variables = array_merge($variables, array(
                '%ssl_port%'    => $input->getOption('ssl-port'),
                '%certificate%' => $input->getOption('ssl-crt'),
                '%key%'         => $input->getOption('ssl-key')
            ));
        }

        return $variables;
    }

    protected function _getTemplate(InputInterface $input, $application = 'apache')
    {
        if ($template = $input->getOption(sprintf('%s-template', $application)))
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

            switch($application)
            {
                case 'nginx':
                    $file = 'nginx.conf';
                    break;
                case 'apache':
                default:
                    $file = 'apache.conf';
                    break;
            }
        }

        $template = file_get_contents(sprintf('%s/%s', $path, $file));

        if (!$input->getOption('disable-ssl'))
        {
            if (file_exists($input->getOption('ssl-crt')) && file_exists($input->getOption('ssl-key')))
            {
                $file = str_replace('.conf', '.ssl.conf', $file);

                $template .= "\n\n" . file_get_contents(sprintf('%s/%s', $path, $file));
            }
            else throw new \Exception('Unable to enable SSL for the site: one or more certificate files are missing.');
        }

        return $template;
    }
}
