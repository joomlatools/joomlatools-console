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
use Symfony\Component\Yaml\Yaml;

class Configurable extends Command
{
    protected $_config = null;

    /**
     * Registers the --preflight and --postflight options on every command that
     * inherits from this class.
     *
     * They are added here rather than in each configure() so that commands do
     * not have to opt in, and so the values can be set per command in
     * config.yaml exactly like any other option:
     *
     *     site:create:
     *       postflight: site-php %site% auto
     *
     * @param string|null $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->addOption(
            'preflight',
            null,
            InputOption::VALUE_REQUIRED,
            'Shell command to run before this command. A non-zero exit aborts the command. %argument% and %option% placeholders are replaced with the values the command was called with.',
            null
        );

        $this->addOption(
            'postflight',
            null,
            InputOption::VALUE_REQUIRED,
            'Shell command to run after this command succeeds. %argument% and %option% placeholders are replaced with the values the command ran with, e.g. "site-php %site% auto".',
            null
        );
    }

    /**
     * Runs the preflight hook, the command, then the postflight hook.
     *
     * The two hooks deliberately treat failure differently:
     *
     * - preflight is a gate. A non-zero exit aborts before the command runs,
     *   mirroring how a Joomla installer script's preflight() aborts an
     *   install. Its whole purpose is to be able to say no.
     * - postflight is a follow-up. A non-zero exit is reported but does not
     *   change the command's status, because the command itself succeeded and
     *   claiming otherwise would be misleading.
     *
     * Both run from run() rather than execute(), so postflight fires only once
     * the command has completely finished. For site:create that matters: the
     * virtual host is written partway through execute(), well before the CMS is
     * installed, so a hook attached any earlier would observe a half-populated
     * site directory.
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // Bind the input before reading the preflight option. Symfony does this
        // inside Command::run(), which has not been reached yet, and until it
        // happens $input->getOption() raises "option does not exist". Invalid
        // input is swallowed here on purpose so that parent::run() below is the
        // one that reports it, with its usual message.
        $this->mergeApplicationDefinition();

        try {
            $input->bind($this->getDefinition());
        } catch (\Exception $e) {
            // Reported by parent::run().
        }

        $preflight = $this->_runHook($input, $output, 'preflight');

        if ($preflight !== 0)
        {
            $output->writeln(sprintf('<error>Preflight exited with status %d. Aborting.</error>', $preflight));

            return $preflight;
        }

        $status = parent::run($input, $output);

        if ($status === 0)
        {
            $postflight = $this->_runHook($input, $output, 'postflight');

            if ($postflight !== 0) {
                $output->writeln(sprintf('<comment>Postflight exited with status %d.</comment>', $postflight));
            }
        }

        return $status;
    }

    /**
     * Substitutes %placeholders% into a hook command and executes it.
     *
     * @return int The hook's exit status, or 0 when no hook is set.
     */
    protected function _runHook(InputInterface $input, OutputInterface $output, $name)
    {
        $command = $input->getOption($name);

        if (empty($command)) {
            return 0;
        }

        $command = str_replace(
            array_keys($this->_getHookReplacements($input)),
            array_values($this->_getHookReplacements($input)),
            $command
        );

        if ($output->isVerbose()) {
            $output->writeln(sprintf('<info>Running %s:</info> %s', $name, $command));
        }

        $status = 0;
        passthru($command, $status);

        return $status;
    }

    /**
     * Builds the %placeholder% map from the arguments and options the command
     * was called with.
     *
     * Values are shell escaped but the surrounding command string is not, so a
     * hook can still use shell syntax such as && to chain commands.
     *
     * Note that %root% is only available to postflight: the site directory is
     * resolved while the command executes, so it is not yet known at preflight.
     */
    protected function _getHookReplacements(InputInterface $input)
    {
        $replacements = array();

        foreach (array($input->getArguments(), $input->getOptions()) as $values)
        {
            foreach ($values as $key => $value)
            {
                // Substituting a hook into itself would be nonsense.
                if ($key === 'preflight' || $key === 'postflight') {
                    continue;
                }

                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }

                if (is_scalar($value)) {
                    $replacements['%' . $key . '%'] = escapeshellarg((string) $value);
                }
            }
        }

        if (property_exists($this, 'target_dir') && !empty($this->target_dir)) {
            $replacements['%root%'] = escapeshellarg($this->target_dir);
        }

        return $replacements;
    }

    public function addArgument($name, $mode = null, $description = '', $default = null)
    {
        if ($mode != InputOption::VALUE_NONE) {
            $default = $this->_getConfigOverride($name) ?? $default;
        }

        return parent::addArgument($name, $mode, $description, $default);
    }

    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        if ($mode != InputOption::VALUE_NONE) {
            $default = $this->_getConfigOverride($name) ?? $default;
        }

        return parent::addOption($name, $shortcut, $mode, $description, $default);
    }

    protected function _getConfigOverride($name)
    {
        $override = null;

        if (is_null($this->_config))
        {
            $file = sprintf('%s/.joomlatools/console/config.yaml', trim(`echo ~`));

            if (file_exists($file)) 
            {
                $file_contents = \file_get_contents($file);
                $env = $_ENV;
                
                // Replace longest keys first
                uksort($env, function($a, $b){
                    return strlen($b) - strlen($a);
                });
                
                // Replace environment variables in the file
                foreach ($env as $key => $value) {
                    $file_contents = \str_replace('$'.$key, $value, $file_contents);
                }

                $this->_config = Yaml::parse($file_contents);
            } else {
                $this->_config = false;
            }
        }

        $config = $this->_config;

        if (is_array($config))
        {
            if ($command = $this->getName())
            {
                if (isset($config[$command][$name])) {
                    $override = $config[$command][$name];
                }
            }

            // Look for global settings

            if (is_null($override) && isset($config['globals'][$name])) {
                $override = $config['globals'][$name];
            }
        }

        return $override;
    }
}