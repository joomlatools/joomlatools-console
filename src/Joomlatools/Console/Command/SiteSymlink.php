<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SiteSymlink extends SiteAbstract
{
    protected $symlink = array();

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('site:symlink')
            ->setDescription('Symlink projects into a site')
            ->addArgument(
                'symlink',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of folders to symlink from projects folder'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                sprintf('%s/Projects', trim(`echo ~`))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->symlink = $input->getArgument('symlink');

        $this->check($input, $output);
        $this->symlinkProjects($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        static $dependencies = array(
            'extman'  => array('koowa'),
            'docman'  => array('extman', 'koowa', 'com_files'),
            'fileman' => array('extman', 'koowa', 'com_files'),
            'logman'  => array('extman', 'koowa', 'com_activities')
        );

        $project_folder = $input->getOption('projects-dir');
        $destination    = $this->target_dir;

        $projects = array();
        foreach ($this->symlink as $symlink)
        {
            $projects[] = $symlink;
            if (array_key_exists($symlink, $dependencies)) {
                $projects = array_merge($projects, $dependencies[$symlink]);
            }
        }

        // If we are symlinking Koowa, we need to create this structure to allow multiple symlinks in them
        if (in_array('koowa', $projects))
        {
            $dirs = array($this->target_dir.'/libraries/koowa/components', $this->target_dir.'/media/koowa');
            foreach ($dirs as $dir)
            {
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
            }
        }

        foreach ($projects as $project)
        {
            $root = $project_folder.'/'.$project;

            if (!is_dir($root)) {
                continue;
            }

            if (is_dir($root.'/code')) {
                $root = $root.'/code';
            }

            $iterator = new Symlink\Iterator($root, $destination);

            while ($iterator->valid()) {
                $iterator->next();
            }
        }
    }
}