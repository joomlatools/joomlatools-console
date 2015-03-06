<?php
namespace Joomlatools\Console;

class Application extends \Symfony\Component\Console\Application
{
    /**
     * joomla-console version
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Application name
     *
     * @var string
     */
    const NAME = 'Joomla Console tools';

    /**
     * @inherits
     *
     * @param string $name
     * @param string $version
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct(self::NAME, self::VERSION);
    }
}