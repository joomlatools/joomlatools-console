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
                'Alternative Git repository to clone. To use joomlatools/platform, use --repo=platform.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        $this->versions = $this->getApplication()->get('versions');;

        if ($input->getOption('repo')) {
            $this->versions->setRepository($input->getOption('repo'));
        }

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

                throw new \RuntimeException(sprintf('Downloaded tarball "%s" could not be verified. A common cause is an interrupted download: check your internet connection and try again.', basename($tarball)));
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

            if ($isPlatform) {
                `cd $this->target_dir; composer install -q`;
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
        $result = strtolower($version);

        if (strtolower($version) === 'latest') {
            $result = $this->versions->getLatestRelease();
        }
        elseif ($version != 'none')
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

        if (file_exists($cache) && !$this->versions->isBranch($this->version)) {
            return $cache;
        }

        $repository = $this->versions->getRepository();

        // We can be certain that the joomla-cms repository is a public GitHub repository
        // so we can download the files straight over HTTP.
        // We have no clue about anything else, so we clone those locally and fall back on git-archive
        if ($repository == 'https://github.com/joomla/joomla-cms.git')
        {
            $output->writeln("<info>Downloading Joomla $this->version - this could take a few minutes...</info>");

            $result = $this->_downloadJoomlaCMS($cache);

            if (!$result) {
                throw new \RuntimeException(sprintf('Failed to download Joomla %s', $this->version));
            }
        }
        else
        {
            $clone = $this->versions->getCacheDirectory() . '/source';
            if (!file_exists($clone))
            {
                $output->writeln("<info>Cloning $repository - this could take a few minutes...</info>");

                `git clone --bare --mirror "$repository" "$clone"`;
            }

            if ($this->versions->isBranch($this->version))
            {
                $output->writeln("<info>Fetching latest changes from $repository - this could take a few minutes...</info>");

                `git --git-dir "$clone" --bare fetch`;
            }

            `git --git-dir "$clone" archive --prefix=base/ $this->version | gzip >"$cache"`;
        }

        return $cache;
    }

    /**
     * Downloads joomla-cms codebase from github
     *
     * @param $target
     * @return bool
     */
    protected function _downloadJoomlaCMS($target)
    {
        if ($this->versions->isBranch($this->version)) {
            $url = 'http://github.com/joomla/joomla-cms/tarball/'.$this->version;
        }
        else $url = 'https://github.com/joomla/joomla-cms/archive/'.$this->version.'.tar.gz';

        $bytes = file_put_contents($target, fopen($url, 'r'));
        if ($bytes === false || $bytes == 0) {
            return false;
        }

        return true;
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