<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Versions;
use Joomlatools\Console\Joomla\Util;

class Download extends AbstractSite
{
    /**
     * Joomla version to install
     *
     * @var string
     */
    protected $version;

    /**
     * Available Joomla versions
     *
     * @var Versions
     */
    protected $versions;

    /**
     * @var OutputInterface
     */
    protected $output = null;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:download')
            ->setDescription('Download and extract the given Joomla version')
            ->addOption(
                'release',
                null,
                InputOption::VALUE_REQUIRED,
                "Joomla version. Can be a release number (2, 3.2, ..) or branch name. Run `joomla versions` for a full list.\nUse \"none\" for an empty virtual host.",
                'latest'
            )
            ->addOption(
                'refresh',
                null,
                InputOption::VALUE_NONE,
                'Update the list of available tags and branches from the Joomla repository'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Clear the downloaded files cache'
            )
            ->addOption(
                'repo',
                null,
                InputOption::VALUE_REQUIRED,
                'Alternative Git repository to clone. Also accepts a gzipped tar archive instead of a Git repository. To use joomlatools/platform, use --repo=platform. For Kodekit Platform, use --repo=kodekit-platform.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        parent::execute($input, $output);

        $this->check($input, $output);

        $this->versions = new Versions();

        $repo = $input->getOption('repo');

        if (empty($repo)) {
            $repo = Versions::REPO_JOOMLA_CMS;
        }

        $this->versions->setRepository($repo);

        if ($input->getOption('refresh')) {
            $this->versions->refresh();
        }

        if ($input->getOption('clear-cache')) {
            $this->versions->clearcache($output);
        }

        $this->setVersion($input->getOption('release'));

        if ($this->version != 'none')
        {
            $tarball = $this->_getTarball($output);

            if (!$this->_isValidTarball($tarball))
            {
                if (file_exists($tarball)) {
                    unlink($tarball);
                }

                throw new \RuntimeException(sprintf('Downloaded tar archive "%s" could not be verified. A common cause is an interrupted download: check your internet connection and try again.', basename($tarball)));
            }

            if (!file_exists($this->target_dir)) {
                `mkdir -p $this->target_dir`;
            }

            `cd $this->target_dir; tar xzf $tarball --strip 1`;

            if ($this->versions->isBranch($this->version)) {
                unlink($tarball);
            }

            $isPlatform = Util::isPlatform($this->target_dir);

            $directory = $this->target_dir. ($isPlatform ? '/web' : '');
            if (file_exists($directory.'/htaccess.txt')) {
                `cp $directory/htaccess.txt $directory/.htaccess`;
            }

            if ($isPlatform || Util::isKodekitPlatform($this->target_dir)) {
                `cd $this->target_dir; composer --no-interaction install -q`;
            }
        }
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (count(glob("$this->target_dir/*", GLOB_NOSORT)) !== 0) {
            throw new \RuntimeException(sprintf('Target directory %s is not empty', $this->target_dir));
        }
    }

    public function setVersion($version)
    {
        if (!$this->versions->isGitRepository())
        {
            $this->version = 'current';
            return;
        }

        if ($version == 'none')
        {
            $this->version = $version;
            return;
        }

        $result = strtolower($version);

        if (strtolower($version) === 'latest') {
            $result = $this->versions->getLatestRelease();
        }
        else
        {
            $length = strlen($version);
            $format = is_numeric($version) || preg_match('/^v?\d(\.\d+)?$/im', $version);

            if (substr($version, 0, 1) == 'v') {
                $length--;
            }

            if ( ($length == 1 || $length == 3) && $format)
            {
                $result = $this->versions->getLatestRelease($version);

                if($result == '0.0.0') {
                    $result = $version.($length == 1 ? '.0.0' : '.0');
                }
            }
        }

        if (!$this->versions->isBranch($result))
        {
            $isTag = $this->versions->isTag($result);

            if (!$isTag)
            {
                $original = $result;
                if (substr($original, 0, 1) == 'v') {
                    $result = substr($original, 1);
                }
                else $result = 'v' . $original;

                if (!$this->versions->isTag($result)) {
                    throw new \RuntimeException(sprintf('Failed to find tag or branch "%s". Please refresh the version list first: `joomla versions --refresh`', $original));
                }
            }
        }

        $this->version = $result;
    }

    protected function _getTarball(OutputInterface $output)
    {
        $tar   = $this->version.'.tar.gz';
        // Replace forward slashes with a dash, otherwise the path looks like it contains more subdirectories
        $tar = str_replace('/', '-', $tar);

        $cache = $this->versions->getCacheDirectory().'/'.$tar;

        $repository = $this->versions->getRepository();

        if ($this->versions->isGitRepository())
        {
            if (file_exists($cache) && !$this->versions->isBranch($this->version)) {
                return $cache;
            }

            $scheme    = strtolower(parse_url($repository, PHP_URL_SCHEME));
            $isGitHub  = strtolower(parse_url($repository, PHP_URL_HOST)) == 'github.com';
            $extension = substr($repository, -4);

            if (in_array($scheme, array('http', 'https')) && $isGitHub && $extension != '.git') {
                $result = $this->_downloadFromGitHub($cache);
            }
            else $result = $this->_clone($cache);
        }
        else $result = $this->_download($cache);

        if (!$result) {
            throw new \RuntimeException(sprintf('Failed to download source files for Joomla %s', $this->version));
        }

        return $cache;
    }

    /**
     * Downloads codebase from GitHub via HTTP
     *
     * @param $target
     * @return bool
     */
    protected function _downloadFromGitHub($target)
    {
		$url = $this->versions->getRepository();

        if ($this->versions->isBranch($this->version)) {
            $url .= '/tarball/' . $this->version;
        }
        else $url .= '/archive/'.$this->version.'.tar.gz';

        $this->output->writeln("<info>Downloading $url - this could take a few minutes ..</info>");

        $bytes = file_put_contents($target, fopen($url, 'r'));

        return (bool) $bytes;
    }

    /**
     * Downloads codebase via HTTP
     *
     * @param $target
     * @return bool
     */
    protected function _download($target)
    {
        $url  = $this->versions->getRepository();

        $this->output->writeln("<info>Downloading $url - this could take a few minutes ..</info>");

        $bytes = file_put_contents($target, fopen($url, 'r'));

        return (bool) $bytes;
    }

    /**
     * Clone Git repository and create tarball
     *
     * @param $target
     * @return bool
     */
    protected function _clone($target)
    {
        if (substr($target, -3) == '.gz') {
            $target = substr($target, 0, -3);
        }

        $clone      = $this->versions->getCacheDirectory() . '/source';
        $repository = $this->versions->getRepository();

        if (!file_exists($clone))
        {
            $this->output->writeln("<info>Cloning $repository - this could take a few minutes ..</info>");

            `git clone --recursive "$repository" "$clone"`;
        }

        if ($this->versions->isBranch($this->version))
        {
            $this->output->writeln("<info>Fetching latest changes from $repository - this could take a few minutes ..</info>");

            `git --git-dir "$clone/.git" fetch`;
        }

        $this->output->writeln("<info>Creating $this->version archive for $repository ..</info>");

        `git --git-dir "$clone/.git" archive --prefix=base/ $this->version >"$target"`;

        // Make sure to include submodules
        if (file_exists("$clone/.gitmodules"))
        {
            exec("cd $clone && (git submodule foreach) | while read entering path; do echo \$path; done", $output, $return_var);

            if (is_array($output))
            {
                foreach ($output as $module)
                {
                    $module = trim($module, "'");
                    $path   = "$clone/$module";

                    $cmd = "cd $path && git archive --prefix=base/$module/ HEAD > /tmp/$module.tar && tar --concatenate --file=\"$target\" /tmp/$module.tar";
                    exec($cmd);

                    @unlink("/tmp/$module.tar");
                }
            }
        }

        `gzip $target`;

        return (bool) @filesize("$target.gz");
    }

    /**
     * Validate the given gzipped tarball
     *
     * @param $file
     * @return bool
     */
    protected function _isValidTarball($file)
    {
        if (!file_exists($file)) {
            return false;
        }

        $commands = array(
            "gunzip -t $file",
            "tar -tzf $file >/dev/null"
        );

        foreach ($commands as $command)
        {
            exec($command, $output, $returnVal);

            if ($returnVal != 0) {
                return false;
            }
        }

        return true;
    }
}
