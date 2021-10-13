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

    /**
     * @var InputInterface
     */
    protected $input = null;

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
                'Alternative Git repository to clone. Also accepts a gzipped tar archive instead of a Git repository. To use joomlatools/platform, use --repo=platform.'
            )
            ->addOption(
                'clone',
                null,
                InputOption::VALUE_OPTIONAL,
                'Clone the Git repository instead of creating a copy in the target directory. Use --clone=shallow for a shallow clone or leave empty.',
                true
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input  = $input;

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

        if (strtolower($this->version) == 'none') {
            return 0;
        }

        if ($input->hasParameterOption('--clone')) {
            $this->_setupClone();
        }
        else $this->_setupCopy();

        $isPlatform = Util::isPlatform($this->target_dir);

        $directory = $this->target_dir. ($isPlatform ? '/web' : '');
        if (file_exists($directory.'/htaccess.txt')) {
            `cp $directory/htaccess.txt $directory/.htaccess`;
        }

        if ($isPlatform ) {
            `cd $this->target_dir; composer --no-interaction install -q`;
        }

        return 0;
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

    protected function _setupCopy()
    {
        $tarball = $this->_getTarball();

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

        if (!$this->versions->isBranch($this->version) && \version_compare($this->version, '4.0.0', '>=')) {
            `cd $this->target_dir; tar xzf $tarball`;    
        } else {
            `cd $this->target_dir; tar xzf $tarball --strip 1`;
        }

        if ($this->versions->isBranch($this->version)) {
            unlink($tarball);
        }
    }

    protected function _setupClone()
    {
        if (!$this->versions->isGitRepository()) {
            throw new \RuntimeException(sprintf('The --clone flag requires a valid Git repository'));
        }

        $this->_clone($this->target_dir, $this->version);
    }

    protected function _getTarball()
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

            if ($repository === 'https://github.com/joomla/joomla-cms' && !$this->versions->isBranch($this->version)
                && \version_compare($this->version, '4.0.0', '>=')) {
                $result = $this->_downloadJoomlaRelease($cache);           
            } else {
                $scheme    = strtolower(parse_url($repository, PHP_URL_SCHEME));
                $isGitHub  = strtolower(parse_url($repository, PHP_URL_HOST)) == 'github.com';
                $extension = substr($repository, -4);
    
                if (in_array($scheme, array('http', 'https')) && $isGitHub && $extension != '.git') {
                    $result = $this->_downloadFromGitHub($cache);
                }
                else
                {
                    $directory = $this->versions->getCacheDirectory() . '/source';
    
                    if ($this->_clone($directory)) {
                        $result = $this->_archive($directory, $cache);
                    }
                }
            }
        }
        else $result = $this->_download($cache);

        if (!$result) {
            throw new \RuntimeException(sprintf('Failed to download source files for Joomla %s', $this->version));
        }

        return $cache;
    }

    protected function _downloadJoomlaRelease($target)
    {
		$url = $this->versions->getRepository();

        $url .= "/releases/download/{$this->version}/Joomla_{$this->version}-Stable-Full_Package.tar.gz";
        
        $this->output->writeln("<info>Downloading $url - this could take a few minutes ..</info>");

        $opts = array(
            'http' => array('method' => 'GET',
            'max_redirects' => '20')
        );
     
     $context = stream_context_create($opts);
        $bytes = file_put_contents($target, fopen($url, 'r', false, $context));

        return (bool) $bytes;
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
     * Clone Git repository to $target directory
     *
     * @param $target Target directory
     * @param $tag    Tag or branch to check out
     * @return bool
     */
    protected function _clone($directory, $tag = false)
    {
        $repository = $this->versions->getRepository();

        if (!file_exists($directory))
        {
            $this->output->writeln("<info>Cloning $repository - this could take a few minutes ..</info>");

            $option = strtolower($this->input->getOption('clone'));
            $args   = $option == 'shallow' ? '--depth 1' : '';

            if (is_string($tag)) {
                $args .= sprintf(' --branch %s', escapeshellarg($tag));
            }

            $command = sprintf("git clone %s --recursive %s %s", $args, escapeshellarg($repository), escapeshellarg($directory));

            exec($command, $result, $exit_code);

            if ($exit_code > 0) {
                return false;
            }
        }

        if ($this->versions->isBranch($this->version))
        {
            $this->output->writeln("<info>Fetching latest changes from $repository - this could take a few minutes ..</info>");

            exec(sprintf("git --git-dir %s fetch", escapeshellarg("$directory/.git")), $result, $exit_code);

            if ($exit_code > 0) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Create tarball from cloned Git repository.
     *
     * @param $source   Git repository
     * @param $filename Output filename
     * @return bool
     */
    protected function _archive($source, $filename)
    {
        $repository = $this->versions->getRepository();

        $this->output->writeln("<info>Creating $this->version archive for $repository ..</info>");

        if (substr($filename, -3) == '.gz') {
            $filename = substr($filename, 0, -3);
        }

        `git --git-dir "$source/.git" archive --prefix=base/ $this->version >"$filename"`;

        // Make sure to include submodules
        if (file_exists("$source/.gitmodules"))
        {
            exec("cd $source && (git submodule foreach) | while read entering path; do echo \$path; done", $result, $return_var);

            if (is_array($result))
            {
                foreach ($result as $module)
                {
                    $module = trim($module, "'");
                    $path   = "$source/$module";

                    $cmd = "cd $path && git archive --prefix=base/$module/ HEAD > /tmp/$module.tar && tar --concatenate --file=\"$filename\" /tmp/$module.tar";
                    exec($cmd);

                    @unlink("/tmp/$module.tar");
                }
            }
        }

        `gzip $filename`;

        return (bool) @filesize("$filename.gz");
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
            exec($command, $result, $returnVal);

            if ($returnVal != 0) {
                return false;
            }
        }

        return true;
    }
}
