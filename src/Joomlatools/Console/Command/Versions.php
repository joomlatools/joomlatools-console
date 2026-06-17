<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\TableHelper;
use Joomlatools\Console\Joomla\Util;

class Versions extends Command
{
    const REPO_JOOMLA_CMS = 'https://github.com/joomla/joomla-cms';

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
    protected $repository = self::REPO_JOOMLA_CMS;

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
                'Alternative Git repository to clone. Also accepts a gzipped tar archive instead of a Git repository.',
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

            $table = new Table($output);

            $table->setHeaders(array($header))
                ->setRows($chunks)
                ->setStyle('compact')
                ->render($output);
        }

        return 0;
    }

    public function setRepository($repository)
    {
        $this->repository = $repository;

        self::$file = Util::getWritablePath() . '/cache/' . md5($this->repository) . '/.versions';
    }

    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Check if the repository is a valid Git repository.
     *
     * @return bool
     */
    public function isGitRepository()
    {
        $cmd = "GIT_SSH_COMMAND=\"ssh -oBatchMode=yes\" GIT_ASKPASS=/bin/echo git ls-remote $this->repository 2>&1";
        exec($cmd, $output, $returnVal);

        return $returnVal === 0;
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
        if (file_exists(self::$file)) {
            unlink(self::$file);
        }

        $cmd = "GIT_SSH_COMMAND=\"ssh -oBatchMode=yes\" GIT_ASKPASS=/bin/echo git ls-remote $this->repository 2>&1 | grep -E 'refs/(tags|heads)' | grep -v '{}'";
        exec($cmd, $refs, $returnVal);

        if ($returnVal != 0) {
            $refs = array();
        }

        $versions = array('tags' => array(), 'heads' => array());
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
        if (!file_exists(self::$file)) {
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

        if (!$this->isGitRepository()) {
            return 'current';
        }

        $versions = $this->_getVersions();

        if (!isset($versions['tags'])) {
            return 'master';
        }

        $major = $prefix;
        if (!is_null($major) && substr($major, 0, 1) == 'v') {
            $major = substr($major, 1);
        }

        // Collect every tag that looks like a stable release, keyed by tag name
        // with its comparable (v-stripped, lower-cased) version as the value.
        $candidates = array();
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

            $candidates[$version] = strtolower($compare);
        }

        // Highest version first, so the newest downloadable release wins.
        uasort($candidates, function($a, $b) {
            return version_compare($b, $a);
        });

        foreach($candidates as $version => $compare)
        {
            // Not every Git tag is a published release: Joomla's CI pushes tags
            // (e.g. 5.4.112) that have no downloadable package, which would later
            // fail with an "interrupted download" error. Skip any tag we can
            // positively confirm has no release asset.
            if($this->_requiresReleaseAsset($compare) && $this->_releaseExists($version) === false) {
                continue;
            }

            return $version;
        }

        return 'master';
    }

    /**
     * Whether the latest release for the given version is served from the
     * GitHub releases endpoint, and therefore must have a published asset.
     *
     * @param string $version
     * @return bool
     */
    protected function _requiresReleaseAsset($version)
    {
        return $this->repository === self::REPO_JOOMLA_CMS && version_compare($version, '4.0.0', '>=');
    }

    /**
     * Check whether a published release package exists for the given tag.
     *
     * @param string $version
     * @return bool|null  True if the asset exists, false if it is missing,
     *                    null if it could not be determined (e.g. no network).
     */
    protected function _releaseExists($version)
    {
        $url = $this->repository . "/releases/download/{$version}/Joomla_{$version}-Stable-Full_Package.tar.gz";

        $context = stream_context_create(array(
            'http' => array(
                'method'          => 'HEAD',
                'follow_location' => 1,
                'max_redirects'   => 20,
                'ignore_errors'   => true,
                'timeout'         => 10
            )
        ));

        $headers = @get_headers($url, false, $context);

        if (!$headers) {
            return null;
        }

        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return $status >= 200 && $status < 300;
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
