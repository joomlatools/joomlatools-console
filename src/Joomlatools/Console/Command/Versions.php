<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\TableHelper;
use Joomlatools\Console\Joomla\Util;

class Versions extends Command
{
	const JOOMLATOOLS_REPO = 'git@github.com:joomlatools/joomlatools-platform.git';

	const JOOMLA_REPO = 'https://github.com/joomla/joomla-cms.git';
    /**
     * Cache file
     *
     * @var string
     */
    protected static $file;

    /**
     * Git repository to use
     *
     * @var string
     */
    protected $repository = self::JOOMLA_REPO;

    protected function configure()
    {
        if (!self::$file) {
            self::$file = Util::getWritablePath() . '/cache/' . md5($this->repository) . '/.versions';
        }

        $this
            ->setName('versions')
            ->setDescription('Show available versions in Joomla Git repository')
            ->addOption(
                'refresh',
                null,
                InputOption::VALUE_NONE,
                'Refresh the versions cache'
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
                'Alternative Git repository to clone. To use joomlatools/platform, use --repo=platform.',
                $this->repository
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setRepository($input->getOption('repo'));

        if ($input->getOption('refresh')) {
            $this->refresh();
        }

        if ($input->getOption('clear-cache')) {
            $this->clearcache($output);
        }

        $list = $this->_getVersions();

        foreach($list as $ref => $versions)
        {
            $chunks = array_chunk($versions, 4);
            $header = $ref === 'heads' ? "Branches" : "Releases";

            $this->getHelperSet()->get('table')
                ->setHeaders(array($header))
                ->setRows($chunks)
                ->setLayout(TableHelper::LAYOUT_COMPACT)
                ->render($output);
        }
    }

    public function setRepository($repository)
    {
        if ($repository == 'platform') {
            $repository = self::JOOMLATOOLS_REPO;
        }

        $this->repository = $repository;

        self::$file = Util::getWritablePath() . '/cache/' . md5($this->repository) . '/.versions';
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function getCacheDirectory()
    {
        $cachedir = dirname(self::$file);

        if (!file_exists($cachedir)) {
            mkdir($cachedir, 0755, true);
        }

        return $cachedir;
    }

    public function clearcache(OutputInterface $output)
    {
        $cachedir = $this->getCacheDirectory();

        if(!empty($cachedir) && file_exists($cachedir))
        {
            `rm -rf $cachedir/*.tar.gz`;

            $output->writeln("<info>Downloaded version cache has been cleared.</info>");
        }
    }

    public function refresh()
    {
        if(file_exists(self::$file)) {
            unlink(self::$file);
        }

        $cmd = "git ls-remote $this->repository | grep -E 'refs/(tags|heads)' | grep -v '{}'";
        exec($cmd, $refs, $returnVal);

        if ($returnVal != 0) {
            throw new \RuntimeException(sprintf('Failed to connect to repository %s. Check the repository URL and your internet connection and try again.', $this->repository));
        }

        $versions = array();
        $pattern  = '/^[a-z0-9]+\s+refs\/(heads|tags)\/([a-z0-9\.\-_\/]+)$/im';
        foreach($refs as $ref)
        {
            if(preg_match($pattern, $ref, $matches))
            {
                $type = isset($versions[$matches[1]]) ? $versions[$matches[1]] : array();

                if($matches[1] == 'tags')
                {
                    if($matches[2] == '1.7.3' || !preg_match('/^v?\d\.\d+/m', $matches[2])) {
                        continue;
                    }
                }

                $type[] = $matches[2];
                $versions[$matches[1]] = $type;
            }
        }

        if (!file_exists(dirname(self::$file))) {
            mkdir(dirname(self::$file), 0755, true);
        }

        file_put_contents(self::$file, json_encode($versions));
    }

    protected function _getVersions()
    {
        if(!file_exists(self::$file)) {
            $this->refresh();
        }

        $list = json_decode(file_get_contents(self::$file), true);

        if (is_null($list))
        {
            $this->refresh();
            $list = json_decode(file_get_contents(self::$file), true);
        }

        $list = array_reverse($list, true);

        return $list;
    }

    public function getLatestRelease($prefix = null)
    {
        $latest   = '0.0.0';
        $versions = $this->_getVersions();

        if (!isset($versions['tags'])) {
            return 'master';
        }

        $major = $prefix;
        if (!is_null($major) && substr($major, 0, 1) == 'v') {
            $major = substr($major, 1);
        }

        foreach($versions['tags'] as $version)
        {
            if(!preg_match('/v?\d\.\d+\.\d+.*/im', $version) || preg_match('#(?:alpha|beta|rc)#i', $version)) {
                continue;
            }

            $compare = $version;
            if (substr($compare, 0, 1) == 'v') {
                $compare = substr($compare, 1);
            }

            if(!is_null($major) && substr($compare, 0, strlen($major)) != $major) {
                continue;
            }

            if(version_compare($latest, strtolower($compare), '<')) {
                $latest = $version;
            }
        }

        if ($latest == '0.0.0') {
            $latest = 'master';
        }

        return $latest;
    }

    public function isBranch($version)
    {
        $versions = $this->_getVersions();

        return in_array($version, $versions['heads']);
    }

    public function isTag($version)
    {
        $versions = $this->_getVersions();

        return in_array($version, $versions['tags']);
    }
}