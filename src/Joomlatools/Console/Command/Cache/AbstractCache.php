<?php
/**
 * @copyright	Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Cache;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;

use Joomlatools\Console\Joomla\Util;
use Joomlatools\Console\Joomla\Bootstrapper;

abstract class AbstractCache extends AbstractSite
{
    protected $url;

    protected function configure()
    {
        parent::configure();

        $this->addOption(
            'application-url',
            null,
            InputOption::VALUE_REQUIRED,
            "URL to access the site via HTTP. Used for clearing APC cache using a temporary script that we access via HTTP since we cannot clear APC over the command line.",
            'http://localhost/<site>'
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        Bootstrapper::getApplication($this->target_dir);

        $this->url = $input->getOption('application-url');
        if ($this->url == 'http://localhost/<site>') {
            $this->url = 'http://localhost/' . $this->site . '/';
        }

        if (substr($this->url, -1) != '/') {
            $this->url .= '/';
        }

        $this->check($input, $output);
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Site not found: %s', $this->site));
        }

        if (!$this->_isCachingEnabled()) {
            throw new \RuntimeException(sprintf('Caching is disabled on site %s', $this->site));
        }
    }

    protected function _doHTTP($task, $client = 0, array $group = array())
    {
        $random = function($length) {
            $charset ='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $string  = '';
            $count   = strlen($charset);

            while ($length--) {
                $string .= $charset[mt_rand(0, $count-1)];
            }

            return $string;
        };

        $hash = $random(32);

        $this->_createTemporaryScript($task, $hash, $client, $group);

        try
        {
            $ch = curl_init($this->url . 'console-cache.php');

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, array('hash' => $hash));

            $result = curl_exec($ch);

            curl_close($ch);

            if ($result === false) {
                throw new \Exception('cURL request failed');
            }

            $response = json_decode($result);

            if (is_null($response) || $response === false) {
                throw new \Exception('Invalid JSON response');
            }
        }
        catch (\Exception $ex) {
            $response = false;
        }

        $this->_removeTemporaryScript();

        return $response;
    }

    protected function _createTemporaryScript($task, $hash, $client = 0, array $group = array())
    {
        $template   = $this->getApplication()->getDataPath('console-cache.php-tpl');
        $autoloader = realpath(__DIR__.'/../../../../../vendor/autoload.php');

        $contents = file_get_contents($template);
        $contents = sprintf($contents, $autoloader, $this->target_dir, $task, $client, implode(',', $group), $hash);

        $target = Util::isPlatform($this->target_dir) ? $this->target_dir . '/web' : $this->target_dir;
        file_put_contents($target.'/console-cache.php', $contents);

        return $target;
    }

    protected function _removeTemporaryScript()
    {
        $target = Util::isPlatform($this->target_dir) ? $this->target_dir . '/web' : $this->target_dir;

        return unlink($target.'/console-cache.php');
    }

    protected function _isCachingEnabled()
    {
        $config = \JFactory::getConfig();

        return ((int) $config->get('caching') > 0);
    }

    protected function _isAPCEnabled()
    {
        $config = \JFactory::getConfig();

        return ($config->get('cache_handler') === 'apc');
    }
}