<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Extension;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;
use Joomlatools\Console\Command\Symlink\Iterator;

class Symlink extends AbstractSite
{
    protected $symlink = array();

    protected static $_symlinkers = array();

    protected static $_dependencies = array();

    public static function registerDependencies($project, array $dependencies)
    {
        static::$_dependencies[$project] = $dependencies;
    }

    public static function registerSymlinker($symlinker)
    {
        array_unshift(static::$_symlinkers, $symlinker);
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('extension:symlink')
            ->setDescription('Symlink projects into a site')
            ->setHelp(<<<EOL
This command will symlink the directories from the <comment>--projects-dir</comment> directory into the given site. This is ideal for testing your custom extensions while you are working on them.
Your source code should resemble the Joomla directory structure for symlinking to work well. For example, the directory structure of your component should look like this:

 administrator/components/com_foobar
 components/com_foobar
 media/com_foobar

To symlink <comment>com_foobar</comment> into your tesite:

    <info>%command.full_name% testsite com_foobar</info>

You can now use the <comment>extension:register</comment> or <comment>extension:install</comment> commands to make your component available to Joomla.

Note that you can use the <comment>site:create</comment> command to both create a new site and symlink your projects into it using the <comment>--symlink</comment> flag.
EOL
            )
            ->addArgument(
                'symlink',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'A list of directories to symlink from projects directory. Use \'all\' to symlink every directory.'
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

        if (count($this->symlink) == 1 && $this->symlink[0] == 'all')
        {
            $this->symlink = array();
            $source = $input->getOption('projects-dir') . '/*';

            foreach(glob($source, GLOB_ONLYDIR) as $directory) {
                $this->symlink[] = basename($directory);
            }
        }

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
        $project_directory = $input->getOption('projects-dir');

        $projects = array();
        foreach ($this->symlink as $symlink)
        {
            $projects[] = $symlink;
            $projects   = array_unique(array_merge($projects, $this->_getDependencies($symlink)));
        }

        foreach ($projects as $project)
        {
            $result = false;
            $root   = $project_directory.'/'.$project;

            if (!is_dir($root)) {
                continue;
            }

            foreach (static::$_symlinkers as $symlinker)
            {
                $result = call_user_func($symlinker, $root, $this->target_dir, $project, $projects);

                if ($result === true) {
                    break;
                }
            }

            if (!$result) {
                $this->_symlink($root, $this->target_dir, $project, $projects);
            }
        }
    }

    /**
     * Default symlinker
     *
     * @param $project
     * @param $destination
     * @param $name
     * @param $projects
     * @return bool
     */
    protected function _symlink($project, $destination, $name, $projects)
    {
        if (is_dir($project.'/code')) {
            $project .= '/code';
        }

        $iterator = new Iterator($project, $destination);

        while ($iterator->valid()) {
            $iterator->next();
        }

        return true;
    }

    /**
     * Look for the dependencies of the dependency
     *
     * @param  string $project      The directory name of Project
     * @return array                An array of dependencies
     */
    protected function _getDependencies($project)
    {
        $projects     = array();
        $dependencies = static::$_dependencies;

        if(array_key_exists($project, $dependencies) && is_array($dependencies[$project]))
        {
            $projects = $dependencies[$project];

            foreach ($projects as $dependency) {
                $projects = array_merge($projects, $this->_getDependencies($dependency));
            }
        }

        return $projects;
    }
}