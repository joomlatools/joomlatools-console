<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Site;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question;
use Joomlatools\Console\Joomla\Util;

abstract class AbstractSite extends Command
{
    protected $site;
    protected $www;

    protected $target_dir;

    protected static $files;

    protected function configure()
    {
        if (empty(self::$files)) {
            self::$files = Util::getTemplatePath() . '/bin/.files';
        }

        $this->addArgument(
            'site',
            InputArgument::REQUIRED,
            'Alphanumeric site name. Also used in the site URL with .test domain'
        )->addOption(
            'www',
            null,
            InputOption::VALUE_REQUIRED,
            "Web server root",
            '/var/www'
        )
        ->addOption(
            'use-webroot-dir',
            null,
            InputOption::VALUE_NONE,
            "Uses directory specified with --www as the site install dir"
         )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site       = $input->getArgument('site');
        $this->www        = $input->getOption('www');

        if ($input->getOption('use-webroot-dir')) {
            $this->target_dir = $this->www;
        } else {
            $this->target_dir = $this->www.'/'.$this->site;
        }

        return 0;
    }

    /**
     * Prompt user to fill in a value
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $label    string          The description of the value
     * @param $default  string|array    The default value. If array given, question will be multiple-choice and the first item will be default. Can also be empty.
     * @param bool $required
     * @param bool $hidden  Hide user input (useful for passwords)
     *
     * @return string   Answer
     */
    protected function _ask(InputInterface $input, OutputInterface $output, $label, $default = '', $required = false, $hidden = false)
    {
        $helper  = $this->getHelper('question');
        $text    = $label;

        if (is_array($default)) {
            $defaultValue = $default[0];
        }
        else $defaultValue = $default;

        if (!empty($defaultValue)) {
            $text .= ' [default: <info>' . $defaultValue . '</info>]';
        }

        $text .= ': ';

        if (is_array($default)) {
            $question = new Question\ChoiceQuestion($text, $default, 0);
        }
        else $question = new Question\Question($text, $default);

        if ($hidden === true) {
            $question->setHidden(true);
        }

        $answer = $helper->ask($input, $output, $question);

        if ($required && empty($answer)) {
            return $this->_ask($input, $output, $label, $default, $hidden);
        }

        return $answer;
    }
}
