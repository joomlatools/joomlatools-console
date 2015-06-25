<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomla-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Versions;

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
                'joomla',
                null,
                InputOption::VALUE_REQUIRED,
                "Joomla version. Can be a release number (2, 3.2, ..) or branch name. Run `joomla versions` for a full list.\nUse \"none\" for an empty virtual host.",
                'latest'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Update the list of available tags and branches from the Joomla repository'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->check($input, $output);

        $this->versions = new Versions();

        if ($input->getOption('clear-cache')) {
            $this->versions->refresh();
        }

        $this->setVersion($input->getOption('joomla'));

        if ($this->version != 'none')
        {
            $tarball = $this->getTarball($output);
            if(!file_exists($tarball)) {
                throw new \RuntimeException(sprintf('File %s does not exist', $tarball));
            }

            if (!file_exists($this->target_dir)) {
                `mkdir -p $this->target_dir`;
            }

            `cd $this->target_dir; tar xzf $tarball --strip 1`;

            if ($this->versions->isBranch($this->version)) {
                unlink($tarball);
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
            $format = is_numeric($version) || preg_match('/^\d\.\d+$/im', $version);

            if ( ($length == 1 || $length == 3) && $format)
            {
                $result = $this->versions->getLatestRelease($version);

                if($result == '0.0.0') {
                    $result = $version.($length == 1 ? '.0.0' : '.0');
                }
            }
        }

        $this->version = $result;
    }

    public function getTarball(OutputInterface $output)
    {
        $tar   = $this->version.'.tar.gz';
        $cache = self::$files.'/cache/'.$tar;

        if(file_exists($cache) && !$this->versions->isBranch($this->version)) {
            return $cache;
        }

        if ($this->versions->isBranch($this->version)) {
            $url = 'http://github.com/joomla/joomla-cms/tarball/'.$this->version;
        }
        else {
            $url = 'https://github.com/joomla/joomla-cms/archive/'.$this->version.'.tar.gz';
        }

        $output->writeln("<info>Downloading Joomla $this->version - this could take a few minutes...</info>");
        $bytes = file_put_contents($cache, fopen($url, 'r'));
        if ($bytes === false || $bytes == 0) {
            throw new \RuntimeException(sprintf('Failed to download %s', $url));
        }

        return $cache;
    }
}