Joomla Command Line Tools
=========================

This is a script developed by [Joomlatools](http://joomlatools.com) to ease the management of Joomla sites.

It is designed to work on Linux and MacOS. Windows users can use it in [Joomlatools Vagrant box](https://github.com/joomlatools/joomla-vagrant)

Installation
------------

1. Install using Composer:

 `$ composer global install joomlatools/joomla-console`

1. Tell your system where to find the executable by adding the composer directory to your PATH. Add the following line to your shell configuration file called either .profile, .bash_profile, .bash_aliases, or .bashrc. This file is located in your home folder.

 `$ export PATH="$PATH:~/.composer/vendor/bin"`

1. Verify the installation

 `$ joomla --version`

1. For available options, run:

  `$ joomla --list`

1. Read our [documentation pages](http://developer.joomlatools.com/tools/console/usage.html) to learn more about using the tool.

## Requirements

* Composer
* Joomla version 2.5 and up.

## Contributing

Fork the project, create a feature branch, and send us a pull request.

## Authors

See the list of [contributors](https://github.com/joomlatools/joomla-console/contributors).

## License

The `joomlatools/joomla-console` plugin is licensed under the MPL v2 license - see the LICENSE file for details.
