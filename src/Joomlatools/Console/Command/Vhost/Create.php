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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $site = $input->getArgument('site');

        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        $tmp  = '/tmp/vhost.tmp';

        $variables = $this->_getVariables($input);

        if (is_dir('/etc/apache2/sites-available'))
        {
            $template = $this->_getTemplate($input, 'apache');
            $template = str_replace(array_keys($variables), array_values($variables), $template);

            file_put_contents($tmp, $template);

            `sudo tee /etc/apache2/sites-available/1-$site.conf < $tmp`;
            `sudo a2ensite 1-$site.conf`;

            $restart[] = 'apache';

            @unlink($tmp);
        }

        if (is_dir('/etc/nginx/sites-available'))
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

            `sudo tee /etc/nginx/sites-available/1-$site.conf < $tmp`;
            `sudo ln -fs /etc/nginx/sites-available/1-$site.conf /etc/nginx/sites-enabled/1-$site.conf`;

            $restart[] = 'nginx';

            @unlink($tmp);
        }

        if (Util::isJoomlatoolsBox() && $restart)
        {
            $arguments = implode(' ', $restart);

            `box server:restart $arguments`;
        }
    }

    protected function _getVariables(InputInterface $input)
    {
        $documentroot = Util::isPlatform($this->target_dir) ? $this->target_dir . '/web/' : $this->target_dir;

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
        $path = realpath(__DIR__.'/../../../../../bin/.files/vhosts');

        if ($template = $input->getOption(sprintf('%s-template', $application)))
        {
            if (file_exists($template)) {
                return file_get_contents($template);
            }
            else throw new \Exception(sprintf('Template file %s does not exist.', $template));
        }

        switch($application)
        {
            case 'nginx':
                $file = Util::isKodekitPlatform($this->target_dir) ? 'nginx.kodekit.conf' : 'nginx.conf';
                break;
            case 'apache':
            default:
                $file = 'apache.conf';
                break;
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
