Joomla Command Line Tools
=========================

This is a script developed by [Joomlatools](http://joomlatools.com) to ease the management of Joomla sites.

It is designed to work on Linux and MacOS. Windows users can use it in [Joomlatools Vagrant box](https://github.com/joomlatools/joomla-vagrant)

Installation
------------

1. Download or clone this repository.

1. Make the `joomla` command executable:

    `$ chmod u+x /path/to/joomla-console/bin/joomla`

1. Configure your system to recognize where the executable resides. There are 3 options:
    1. Create a symbolic link in a directory that is already in your PATH, e.g.:

        `$ ln -s /path/to/joomla-console/bin/joomla /usr/bin/joomla`

    1. Explicitly add the executable to the PATH variable which is defined in the the shell configuration file called .profile, .bash_profile, .bash_aliases, or .bashrc that is located in your home folder, i.e.:

        `export PATH="$PATH:/path/to/joomla-console/bin:/usr/local/bin"`

    1. Add an alias for the executable by adding this to you shell configuration file (see list in previous option):

        `$ alias joomla=/path/to/joomla-console/bin/joomla`

    For options 2 and 3 above, you should log out and then back in to apply your changes to your current session.

1. Test that joomla executable is found by your system:

    `$ which joomla`

1. From joomla-console root (/path/to/joomla-console), run Composer to fetch dependencies.

    `$ composer install`

For available options, try running:

    joomla --list
    
Usage 
-----

### Create Sites

To create a site with the latest Joomla version, run:

    joomla site:create testsite

The newly installed site will be available at /var/www/testsite and testsite.dev after that. You can login into your fresh Joomla installation using these credentials: `admin` / `admin`.

By default the web server root is set to _/var/www_. You can pass _--www=/my/server/path_ to commands for custom values.

You can choose the Joomla version or the sample data to be installed:

    joomla site:create testsite --joomla=2.5 --sample-data=blog

You can pick any branch from the Git repository (e.g. master, staging) or any version from 2.5.0 and up using this command.

You can also add your projects into the new site by symlinking. See the Symlinking section below for detailed information.

    joomla site:create testsite --symlink=project1,project2

For more information and available options, try running:

    joomla site:create --help

### Delete Sites

You can delete the sites you have created by running:

    joomla site:delete testsite

### Symlink Extensions

Let's say you are working on your own Joomla component called _Awesome_ and want to develop it with the latest Joomla version.

By default your code is assumed to be in _~/Projects_. You can pass _--projects-dir=/my/code/is/here_ to commands for custom values.

Please note that your source code should resemble the Joomla folder structure for symlinking to work well. For example your administrator section should reside in ~/Projects/awesome/administrator/components/com_awesome.

Now to create a new site, execute the site:create command and add a symlink option:

	joomla site:create testsite --symlink=awesome

Or to symlink your code into an existing site:

	joomla extension:symlink testsite awesome

This will symlink all the folders from the _awesome_ folder into _testsite.dev_.

Run discover install to make your component available to Joomla and you are good to go!

For more information on the symlinker, run:

	joomla extension:symlink  --help

### Install Extensions

You can use discover install on command line to install extensions.

	joomla extension:install testsite com_awesome

You need to use the _element_ name in your extension manifest.

For more information, run:

	joomla extension:install --help
	  
Alternatively, you can install extensions using their installation packages using the `extension:installfile` command. Example:

    joomla extension:installfile testsite /home/vagrant/com_component.v1.x.zip /home/vagrant/plg_plugin.v2.x.tar.gz
    
This will install both the com_component.v1.x.zip and plg_plugin.v2.x.tar.gz packages.

### Register Extensions

With the `extension:register` command you can insert your extension into the `extensions` table without the need for a complete install package with a manifest file.

Like `extension:install`, you should also use what would be the _element_ name from your manifest.

    joomla extension:register testsite com_awesome

The `type` of extension that gets registered is, by default, based on the first 4 characters of the extension argument you pass in. Here are the mappings:

* `com_` => component
* `mod_` => module
* `plg_` => plugin (the `plg_` will get stripped from the element field)
* `lib_` => library
* `pkg_` => package
* `tpl_` => template (the `tpl_` will get stripped from the name and element field)
* `lng_` => language

This example registers an extension of the 'plugin' type:

    joomla extension:register testsite plg_awesome

Alternatively, if you want to use naming without the prefixes you have the option of adding a `type` argument to the end of the command.

    joomla extension:register testsite awesome package

In all cases, if the type is not specified or recognized then the default value, **component**, will be used.

When registering `plugin` type you can use the `--folder` option to specify the plugin group that will get registered with the record. Note that the default is 'system'.

    joomla extension:register testsite myplugin plugin --folder=content
    
For a `language` type extension, you should use the `--element` option to ensure your language files can be loaded correctly. 

	joomla extension:register testsite spanglish language --element=en-GB 
	
Lastly, when registering a `module` type extension, you can use the `--position` option to ensure your module displays where you would like it to. A record gets added to the #_modules table. 

	joomla extension:register testsite mod_awesome --position=debug 

Other options available for all extension types: `--enabled`, `--client_id`

## Extra commands

There a few other commands available for you to try out as well :

* `joomla site:token sitename user` : generates an authentication token for the given `user` to automatically login to `sitename` using the ?auth_token query argument. *Note* requires the [Nooku Framework](https://github.com/nooku/nooku-framework) to be installed in your `site`.
* `joomla versions` : list the available Joomla versions. 
 * Use `joomla versions --refresh` to get the latest tags and branches from the official [Joomla CMS](https://github.com/joomla/joomla-cms) repository.
 * To purge the cache of all Joomla packages, add the `--clear-cache` flag to this command.

## Requirements

* Composer
* Joomla version 2.5 and up.

## Contributing

Fork the project, create a feature branch, and send us a pull request.

## Authors

See the list of [contributors](https://github.com/joomlatools/joomla-console/contributors).

## License

The `joomlatools/joomla-console` plugin is licensed under the MPL v2 license - see the LICENSE file for details.
