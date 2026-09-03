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
                'no-ssl',
                null,
                InputOption::VALUE_NONE,
                'Only create the HTTP virtual host, skipping SSL setup'
            )
            ->addOption(
                'ssl-cert',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the SSL certificate to use. Defaults to a locally-trusted *.test wildcard certificate generated via mkcert.',
                null
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the SSL certificate key to use. Defaults to a locally-trusted *.test wildcard certificate generated via mkcert.',
                null
            )
            ->addOption(
                'template',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom file to use as the Apache vhost configuration. Make sure to include HTTP and SSL directives if you need both.',
                null
            )
            ->addOption('folder',
                null,
                InputOption::VALUE_REQUIRED,
                'The Apache2 vhost folder',
                null
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
            $output->writeln(sprintf('<error>Site not found: %s</error>', $this->site));
            return 99;
        }

        if ($input->getOption('folder') === null) {
            $this->_warnIfVhostFolderNotIncluded($output, $this->_getDefaultVhostFolder());
        }

        $target = $this->_getVhostPath($input);

        $ssl = $input->getOption('no-ssl') ? null : $this->_getSslCertificate($input, $output);

        $variables = $this->_getVariables($input, $ssl);

        if (!is_dir(dirname($target))) {
            if (!@mkdir(dirname($target), 0755, true)) {
                $output->writeln(sprintf('<error>Could not create directory: %s. Please check your permissions or run the command with sudo.</error>', dirname($target)));
                return 99;
            }
        }

        if ($ssl !== null) {
            $this->_warnIfSslNotEnabled($output);
        }

        $template = $this->_getTemplate($input, $ssl);
        $template = str_replace(array_keys($variables), array_values($variables), $template);

        if (!@file_put_contents($target, $template)) {
            $output->writeln(sprintf('<error>Could not write to file: %s. Please check your permissions or run the command with sudo.</error>', $target));
            return 99;
        }

        if ($command = $input->getOption('restart-command')) {
            `$command`;
        }

        return 0;
    }

    protected function _getVhostPath($input)
    {
        $folder = $input->getOption('folder') ?? $this->_getDefaultVhostFolder();
        $folder = str_replace('[site]', $this->site, $folder);
        $file = $input->getOption('filename') ?? $input->getArgument('site').'.conf';

        return $folder.'/'.$file;
    }

    /**
     * When we auto-detected a non-standard vhost folder (eg. a Homebrew Apache install),
     * warn the user if their Apache config doesn't already Include it, since writing the
     * vhost file there alone won't make Apache pick it up.
     */
    protected function _warnIfVhostFolderNotIncluded(OutputInterface $output, $folder)
    {
        if ($folder === '/etc/apache2/sites-enabled') {
            return;
        }

        $config = $this->_getApacheConfigFile();

        if (!$config) {
            return;
        }

        $include = sprintf('Include %s/*.conf', $folder);

        if (!file_exists($config) || strpos(file_get_contents($config), $include) === false) {
            $output->writeln(sprintf(
                '<comment>Using %s for vhost files. If Apache does not already include this folder, add "%s" to %s.</comment>',
                $folder, $include, $config
            ));
        }
    }

    protected function _getVariables(InputInterface $input, $ssl = null)
    {
        $documentroot = $this->target_dir;

        $variables = array(
            '%site%'       => $input->getArgument('site'),
            '%root%'       => $documentroot,
            '%http_port%'  => $input->getOption('http-port'),
            '%ssl_port%'  => $input->getOption('ssl-port'),
            '%log_dir%'    => $this->_getDefaultLogFolder(),
        );

        if ($ssl !== null) {
            $variables['%ssl_cert%'] = $ssl[0];
            $variables['%ssl_key%']  = $ssl[1];
        }

        return $variables;
    }

    protected function _getTemplate(InputInterface $input, $ssl = null)
    {
        if ($template = $input->getOption('template'))
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

            $file = $ssl !== null ? 'apache-ssl.conf' : 'apache.conf';
        }

        $template = file_get_contents(sprintf('%s/%s', $path, $file));

        return $template;
    }

    /**
     * Determine a writable folder to store the locally-trusted SSL certificate in.
     *
     * Mirrors _getDefaultVhostFolder(): stored alongside the vhost config folder so it's
     * writable under the same conditions (no sudo needed on Homebrew Apache, or on
     * traditional Apache installs where the current user already owns the config tree).
     *
     * @return string
     */
    protected function _getDefaultCertificateFolder()
    {
        return dirname($this->_getDefaultVhostFolder()).'/ssl';
    }

    /**
     * Locate or generate a locally-trusted wildcard SSL certificate for *.test domains.
     *
     * Certificates are generated once via mkcert (https://github.com/FiloSottile/mkcert),
     * which installs a local CA into the system/browser trust stores so the resulting
     * certificate is trusted without browser warnings. The same wildcard certificate is
     * reused for every site, since it covers any *.test hostname.
     *
     * @return array|null  [$certPath, $keyPath] or null if SSL could not be set up
     */
    protected function _getSslCertificate(InputInterface $input, OutputInterface $output)
    {
        $cert = $input->getOption('ssl-cert');
        $key  = $input->getOption('ssl-key');

        if ($cert && $key) {
            if (!file_exists($cert) || !file_exists($key)) {
                $output->writeln('<comment>The --ssl-cert/--ssl-key files could not be found. Skipping SSL vhost setup.</comment>');
                return null;
            }

            return array($cert, $key);
        }

        $folder = $this->_getDefaultCertificateFolder();
        $cert   = $folder.'/test.pem';
        $key    = $folder.'/test-key.pem';

        if (file_exists($cert) && file_exists($key)) {
            return array($cert, $key);
        }

        if (!trim((string) shell_exec('which mkcert 2>/dev/null'))) {
            $output->writeln('<comment>mkcert not found - skipping SSL vhost setup. Install it (eg. "brew install mkcert") and re-run vhost:create to enable HTTPS for *.test sites.</comment>');
            return null;
        }

        if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
            $output->writeln(sprintf('<comment>Could not create %s to store the SSL certificate. Skipping SSL vhost setup.</comment>', $folder));
            return null;
        }

        `mkcert -install 2>&1`;

        exec(sprintf(
            'mkcert -cert-file %s -key-file %s "*.test" test 2>&1',
            escapeshellarg($cert), escapeshellarg($key)
        ), $lines, $status);

        if ($status !== 0 || !file_exists($cert) || !file_exists($key)) {
            $output->writeln('<comment>Failed to generate an SSL certificate via mkcert. Skipping SSL vhost setup.</comment>');
            return null;
        }

        $output->writeln(sprintf('<info>Generated a locally-trusted SSL certificate for *.test in %s</info>', $folder));

        return array($cert, $key);
    }

    /**
     * Warn the user if Apache doesn't appear to have mod_ssl enabled, since writing an SSL
     * vhost block alone won't make HTTPS work without it (and "Listen 443") being active.
     *
     * If apachectl itself fails to run (eg. a transient exec/fork hiccup), we can't tell
     * that apart from "mod_ssl isn't loaded" just by getting an empty module list, so the
     * exit code is checked explicitly and the warning is skipped rather than risking a
     * false positive.
     */
    protected function _warnIfSslNotEnabled(OutputInterface $output)
    {
        exec('apachectl -M 2>/dev/null', $modules, $status);

        if ($status !== 0) {
            return;
        }

        foreach ($modules as $line) {
            if (stripos($line, 'ssl_module') !== false) {
                return;
            }
        }

        $output->writeln('<comment>Apache does not appear to have mod_ssl enabled. Enable it (eg. uncomment "LoadModule ssl_module ..." and add "Listen 443" in your Apache configuration) and restart Apache for HTTPS to work.</comment>');
    }
}
