<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Package;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\EnableCompatTrait;
use Joomlatools\Console\Command\Site\AbstractSite;
use Joomlatools\Console\Joomla\Bootstrapper;
use Joomlatools\Console\Joomla\Util;

class Install extends AbstractSite
{
    use EnableCompatTrait;

    protected $packages = array();
    protected $projects_dir;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('package:install')
            ->setDescription('Install a built package zip into a site')
            ->setHelp(<<<EOL
Install a package you have already built. Packages live in the extension folder
under <comment>--projects-dir</comment> (default <comment>~/Projects</comment>):

    <info>joomla package:install testsite textman</info>

When that folder contains more than one zip, the newest (by modification time)
is used. Pass a version to pick a specific build:

    <info>joomla package:install testsite textman:6.1.2</info>

Several packages can be given; they are installed in order:

    <info>joomla package:install testsite joomlatools-framework textman</info>

A filesystem path to a zip is also accepted:

    <info>joomla package:install testsite /path/to/com_textman_v6.1.2.zip</info>

<comment>site:create --install=</comment> runs this command after the site is
created. That list is comma-separated, the same shape as <comment>--symlink</comment>:

    <info>joomla site:create testsite --install=textman</info>
    <info>joomla site:create testsite --install=textman:6.1.2,fileman</info>

This is not <comment>extension:install</comment>, which is the discover-install
path for symlinked extensions.
EOL
            )
            ->addArgument(
                'package',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Extension name (latest zip), name:version, or a path to a zip'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            );

        $this->addEnableCompatOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->packages     = (array) $input->getArgument('package');
        $this->projects_dir = $input->getOption('projects-dir');

        $this->check($input, $output);
        $this->install($input, $output);

        return 0;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function install(InputInterface $input, OutputInterface $output)
    {
        $this->maybeEnableCompat($input, $output);

        foreach ($this->packages as $spec)
        {
            $zip = $this->resolvePackage($spec, $this->projects_dir);
            $output->writeln(sprintf('Installing <info>%s</info> from <comment>%s</comment>', $spec, $zip));
            $this->installZip($zip, $output);
        }
    }

    /**
     * Resolve an extension name, name:version spec, or filesystem path to a zip.
     *
     * @param string $spec
     * @param string $projectsDir
     * @return string Absolute path to the zip
     */
    public function resolvePackage($spec, $projectsDir)
    {
        $expanded = $this->expandHome($spec);

        if (is_file($expanded)) {
            return realpath($expanded);
        }

        $name    = $spec;
        $version = null;

        if (strpos($spec, ':') !== false)
        {
            list($name, $version) = explode(':', $spec, 2);

            if ($version === '' || strcasecmp($version, 'latest') === 0) {
                $version = null;
            }
        }

        $dir = rtrim($projectsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($dir)) {
            throw new \RuntimeException(sprintf('Package directory not found: %s', $dir));
        }

        $zips = array();

        foreach (glob($dir . '/*.zip') ?: array() as $zip)
        {
            if (!is_file($zip)) {
                continue;
            }

            if (strpos(basename($zip), '._') === 0) {
                continue;
            }

            $zips[] = $zip;
        }

        if ($version !== null)
        {
            $zips = array_values(array_filter($zips, function ($zip) use ($version) {
                return $this->zipMatchesVersion($zip, $version);
            }));
        }

        if (!$zips)
        {
            if ($version !== null) {
                throw new \RuntimeException(sprintf('No zip matching version %s in %s', $version, $dir));
            }

            throw new \RuntimeException(sprintf('No zip files in %s', $dir));
        }

        usort($zips, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $zips[0];
    }

    protected function zipMatchesVersion($zip, $version)
    {
        $version = ltrim($version, 'vV');
        $base    = basename($zip);

        return (bool) preg_match('/(^|[^0-9])v?' . preg_quote($version, '/') . '([^0-9]|$)/', $base);
    }

    protected function expandHome($path)
    {
        if ($path === '~') {
            return getenv('HOME');
        }

        if (isset($path[0]) && $path[0] === '~' && isset($path[1]) && $path[1] === '/') {
            return getenv('HOME') . substr($path, 1);
        }

        return $path;
    }

    protected function installZip($zip, OutputInterface $output)
    {
        $local = $this->copyToTmp($zip);

        try
        {
            if (Util::isJoomla4($this->target_dir))
            {
                $result = $this->installViaJoomlaCli($local);

                if ($result !== '') {
                    $output->writeln($result);
                }

                return;
            }

            $app = Bootstrapper::getApplication($this->target_dir);

            ob_start();

            if (class_exists('\Joomla\CMS\Installer\InstallerHelper')) {
                $unpacked = \Joomla\CMS\Installer\InstallerHelper::unpack($local, true);
            } else {
                $unpacked = \JInstallerHelper::unpack($local, true);
            }

            if (empty($unpacked['type']))
            {
                ob_end_clean();
                throw new \RuntimeException(sprintf('Failed to unpack package: %s', $zip));
            }

            $dir       = isset($unpacked['dir']) ? $unpacked['dir'] : $unpacked['extractdir'];
            $installer = $app->getInstaller();
            $result    = $installer->install($dir);

            if (class_exists('\Joomla\CMS\Installer\InstallerHelper')) {
                \Joomla\CMS\Installer\InstallerHelper::cleanupInstall($unpacked['packagefile'], $unpacked['extractdir']);
            } else {
                \JInstallerHelper::cleanupInstall($unpacked['packagefile'], $unpacked['extractdir']);
            }

            ob_end_clean();

            if (!$result) {
                throw new \RuntimeException(sprintf('Failed to install package: %s', $zip));
            }
        }
        finally
        {
            if ($local !== $zip && is_file($local)) {
                @unlink($local);
            }
        }
    }

    protected function installViaJoomlaCli($path)
    {
        $command = sprintf(
            'php %s extension:install --path=%s',
            escapeshellarg($this->target_dir . '/cli/joomla.php'),
            escapeshellarg($path)
        );

        $lines = array();
        exec($command, $lines, $code);
        $text = implode(PHP_EOL, $lines);

        if ($code !== 0)
        {
            throw new \RuntimeException(sprintf(
                "Joomla failed to install %s\n%s",
                $path,
                $text !== '' ? $text : "exit status $code"
            ));
        }

        return $text;
    }

    protected function copyToTmp($zip)
    {
        $tmp = $this->target_dir . '/tmp';

        if (!is_dir($tmp) && !mkdir($tmp, 0755, true) && !is_dir($tmp)) {
            throw new \RuntimeException(sprintf('Cannot create tmp directory: %s', $tmp));
        }

        $dest = $tmp . '/' . basename($zip);

        if (is_file($dest) && realpath($zip) === realpath($dest)) {
            return $dest;
        }

        if (!copy($zip, $dest)) {
            throw new \RuntimeException(sprintf('Failed to copy %s to %s', $zip, $dest));
        }

        return $dest;
    }
}
