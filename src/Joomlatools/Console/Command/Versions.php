<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
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

    protected function configure()
    {
        if (!self::$file) {
            self::$file = realpath(__DIR__.'/../../../../bin/.files/cache').'/.versions';
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('refresh')) {
            $this->refresh();
        }

        if ($input->getOption('clear-cache')) {
            $this->clearcache($output);
        }

        $list = $this->getVersions();

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

    public function clearcache(OutputInterface $output)
    {
        $cachedir = dirname(self::$file);

        if(!empty($cachedir) && file_exists($cachedir))
        {
            `rm -rf $cachedir/*.tar.gz`;
            $output->writeln("<info>Downloaded version cache has been cleared.</info>\n");
        }
    }

    public function refresh()
    {
        if(file_exists(self::$file)) {
            unlink(self::$file);
        }

        $result = `git ls-remote https://github.com/joomla/joomla-cms.git | grep -E 'refs/(tags|heads)' | grep -v '{}'`;
        $refs   = explode(PHP_EOL, $result);

        $versions = array();
        $pattern  = '/^[a-z0-9]+\s+refs\/(heads|tags)\/([a-z0-9\.\-_]+)$/im';
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

        file_put_contents(self::$file, json_encode($versions));
    }

    public function getVersions()
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
        // Find the latest tag
        $latest = '0.0.0';
        $versions = $this->getVersions();

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

        return $latest;
    }

    public function isBranch($version)
    {
        $versions = $this->getVersions();

        return in_array($version, $versions['heads']);
    }
}