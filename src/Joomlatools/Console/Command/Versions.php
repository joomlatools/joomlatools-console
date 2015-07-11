<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\TableHelper;

class Versions extends Command
{
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
    protected $repository = 'https://github.com/joomla/joomla-cms.git';

    protected function configure()
    {
        if (!self::$file) {
            self::$file = realpath(__DIR__.'/../../../../bin/.files/cache').'/' . md5($this->repository) . '/.versions';
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
                InputOption::VALUE_OPTIONAL,
                'Alternative Git repository to clone. To use joomlatools/joomla-platform, use --repo=platform.',
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
            $repository = 'git@github.com:joomlatools/joomla-platform.git';
        }

        $this->repository = $repository;

        self::$file = realpath(__DIR__.'/../../../../bin/.files/cache').'/' . md5($this->repository) . '/.versions';
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

        $result = `git ls-remote $this->repository | grep -E 'refs/(tags|heads)' | grep -v '{}'`;
        $refs   = explode(PHP_EOL, $result);

        $versions = array();
        $pattern  = '/^[a-z0-9]+\s+refs\/(heads|tags)\/([a-z0-9\.\-_\/]+)$/im';
        foreach($refs as $ref)
        {
            if(preg_match($pattern, $ref, $matches))
            {
                $type = isset($versions[$matches[1]]) ? $versions[$matches[1]] : array();

                if($matches[1] == 'tags')
                {
                    if($matches[2] == '1.7.3' || !preg_match('/^\d\.\d+/m', $matches[2])) {
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

        foreach($versions['tags'] as $version)
        {
            if(!preg_match('/\d\.\d+\.\d+.*/im', $version) || preg_match('#(?:alpha|beta|rc)#i', $version)) {
                continue;
            }

            if(!is_null($prefix) && substr($version, 0, strlen($prefix)) != $prefix) {
                continue;
            }

            if(version_compare($latest, strtolower($version), '<')) {
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
}