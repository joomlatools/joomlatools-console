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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $site = $input->getArgument('site');
        $port = $input->getOption('http-port');
        $path = realpath(__DIR__.'/../../../../../bin/.files/');
        $tmp  = '/tmp/vhost.tmp';

        if (is_dir('/etc/apache2/sites-available'))
        {
            $template     = file_get_contents($path.'/vhosts/apache.conf');
            $documentroot = Util::isPlatform($this->target_dir) ? $this->target_dir . '/web/' : $this->target_dir;

            file_put_contents($tmp, sprintf($template, $site, $documentroot, $port));

            if (!$input->getOption('disable-ssl'))
            {
                $ssl_crt  = $input->getOption('ssl-crt');
                $ssl_key  = $input->getOption('ssl-key');
                $ssl_port = $input->getOption('ssl-port');

                if (file_exists($ssl_crt) && file_exists($ssl_key))
                {
                    $template = "\n\n" . file_get_contents($path.'/vhosts/apache.ssl.conf');
                    file_put_contents($tmp, sprintf($template, $site, $documentroot, $ssl_port, $ssl_crt, $ssl_key), FILE_APPEND);
                }
                else $output->writeln('<comment>SSL was not enabled for the site. One or more certificate files are missing.</comment>');
            }

            `sudo tee /etc/apache2/sites-available/1-$site.conf < $tmp`;
            `sudo a2ensite 1-$site.conf`;
            `sudo /etc/init.d/apache2 restart > /dev/null 2>&1`;

            @unlink($tmp);
        }

        if (is_dir('/etc/nginx/sites-available'))
        {
            $socket = $input->getOption('php-fpm-address');

            if (Util::isJoomlatoolsBox() && $port == 80) {
                $port = 81;
            }

            if (Util::isKodekitPlatform($this->target_dir)) {
                $file = $this->target_dir . '/install/nginx/nginx.conf';
            }
            else $file = $path.'/vhosts/nginx.conf';

            $template     = file_get_contents($file);
            $documentroot = Util::isPlatform($this->target_dir) ? $this->target_dir . '/web/' : $this->target_dir;

            file_put_contents($tmp, sprintf($template, $site, $documentroot, $port, $socket));

            if (!$input->getOption('disable-ssl') && !Util::isKodekitPlatform($this->target_dir))
            {
                $ssl_crt  = $input->getOption('ssl-crt');
                $ssl_key  = $input->getOption('ssl-key');
                $ssl_port = $input->getOption('ssl-port');

                if (Util::isJoomlatoolsBox() && $ssl_port == 443) {
                    $ssl_port = 444;
                }

                if (file_exists($ssl_crt) && file_exists($ssl_key))
                {
                    $template = "\n\n" . file_get_contents($path.'/vhosts/nginx.ssl.conf');
                    file_put_contents($tmp, sprintf($template, $site, $documentroot, $ssl_port, $socket, $ssl_crt, $ssl_key), FILE_APPEND);
                }
                else $output->writeln('<comment>SSL was not enabled for the site. One or more certificate files are missing.</comment>');
            }

            `sudo tee /etc/nginx/sites-available/1-$site.conf < $tmp`;
            `sudo ln -fs /etc/nginx/sites-available/1-$site.conf /etc/nginx/sites-enabled/1-$site.conf`;
            `sudo /etc/init.d/nginx restart > /dev/null 2>&1`;

            @unlink($tmp);

            // Also set up the Varnish config on the box for Kodekit Platform
            if (Util::isJoomlatoolsBox() && Util::isKodekitPlatform($this->target_dir))
            {
                $copy = '/etc/varnish/default.vcl-original';
                $vcl  = $this->target_dir . '/component/varnish/resources/varnish/kodekit.vcl';

                if (file_exists($vcl) && !file_exists($copy))
                {
                    if (!is_writable('/etc/varnish/')) {
                        `sudo chown vagrant:vagrant /etc/varnish`;
                    }

                    `cp /etc/varnish/default.vcl $copy`;

                    $template = file_get_contents($vcl);

                    file_put_contents('/etc/varnish/default.vcl', sprintf($template, $port));
                    `sudo service varnish restart > /dev/null 2>&1`;
                }
            }
        }
    }
}