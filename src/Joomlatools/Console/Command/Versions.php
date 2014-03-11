<?php

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\TableHelper;

class Versions extends Command
{
    protected function configure()
    {
        $this
            ->setName('versions')
            ->setDescription('Show available versions in Joomla Git repository')
            ->addOption(
                'refresh',
                null,
                InputOption::VALUE_NONE,
                'Refresh the versions cache'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('refresh')) {
            $this->refresh();
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

    public function getVersions()
    {
        $file = 'versions.cache';

        if(!file_exists($file)) {
            $this->refresh();
        }

        $list = json_decode(file_get_contents($file), true);
        $list = array_reverse($list, true);

        return $list;
    }

    public function refresh()
    {
        $file = 'versions.cache';

        if(file_exists($file)) {
            unlink($file);
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

        file_put_contents($file, json_encode($versions));
    }
}