CHANGELOG
=========

This changelog references the relevant changes (bug and security fixes) done
in 1.x versions.

To get the diff for a specific change, go to https://github.com/joomlatools/joomlatools-console/commit/xxx where xxx is the change hash.
To view the diff between two versions, go to https://github.com/joomlatools/joomlatools-console/compare/v1.0.0...v1.0.1

* 1.5.4 (2019-07-12)
    * Added - Add vhost:alias command [#102](https://github.com/joomlatools/joomlatools-console/issues/102)
    * Added - Create relative symlinks [#103](https://github.com/joomlatools/joomlatools-console/issues/103)
    * Improved - Only execute service restarts on box [#109](https://github.com/joomlatools/joomlatools-console/issues/109)
    * Improved - Allow another template file to be used for vhost:create [#105](https://github.com/joomlatools/joomlatools-console/issues/105)
    * Fixed - Prevent 'Incorrect datetime value' MySQL error [#108](https://github.com/joomlatools/joomlatools-console/pull/108)

* 1.5.3 (2019-03-27)
    * Added - Add option to use existing database [#95](https://github.com/joomlatools/joomlatools-console/pull/95)
    * Fixed - Remove redundant library imports [#99](https://github.com/joomlatools/joomlatools-console/issues/99)

* 1.5.2 (2018-07-31)
    * Improved - Check PHP version before running command [#93](https://github.com/joomlatools/joomlatools-console/pull/93)

* 1.5.1 (2018-06-19)
    * Improved - Upgrade to symfony/console 4.x [#86](https://github.com/joomlatools/joomlatools-console/issues/86)
    * Added - Add --mysql-db-prefix option, deprecating --mysql_db_prefix [#84](https://github.com/joomlatools/joomlatools-console/issues/84)

* 1.5.0 (2018-01-11)
    * Added - Install Composer packages via extension:install [#14](https://github.com/joomlatools/joomlatools-console/issues/14)
    * Added - Add support for Nginx [#75](https://github.com/joomlatools/joomlatools-console/issues/75)
    * Added - Add --clone argument [#36](https://github.com/joomlatools/joomlatools-console/issues/36)
    * Fixed - Update the `#__schema` and `#__extensions` tables after installation [#22](https://github.com/joomlatools/joomlatools-console/issues/22)
    * Fixed - Fix site:token on Joomlatools Platform [#35](https://github.com/joomlatools/joomlatools-console/issues/35)
    * Fixed - Fix default port on Joomlatools Vagrant box [#72](https://github.com/joomlatools/joomlatools-console/issues/72)
    * Fixed - Fix Git URL for platform [#73](https://github.com/joomlatools/joomlatools-console/issues/73)
    * Fixed - extension:install bug when using discover install [#61](https://github.com/joomlatools/joomlatools-console/issues/61)
    * Improved - Replace .dev with .test tld [#64](https://github.com/joomlatools/joomlatools-console/issues/64)
    * Improved - Allow non-Git repositories as source, fixed configuration.php regexes, add --options parameter, better MySQL port handling [#70](https://github.com/joomlatools/joomlatools-console/pull/70)
    * Improved - Store plugins outside of Composer directory [#62](https://github.com/joomlatools/joomlatools-console/issues/62)
 
* 1.4.11 (2017-09-23)
    * Fixed - Add Joomla 3.8 compatibility [#65](https://github.com/joomlatools/joomlatools-console/issues/65)
    * Fixed - Option --www does not work for vhost.conf [#42](https://github.com/joomlatools/joomlatools-console/issues/42)
    * Fixed - Run Composer with --no-interaction flag [#68](https://github.com/joomlatools/joomlatools-console/issues/68)

* 1.4.10 (2017-05-09)
    * Fixed - Joomla 3.7 installation error [#59](https://github.com/joomlatools/joomlatools-console/issues/59)
 
* 1.4.9 (2017-03-07)
    * Fixed - SiteCreate command fails on 3.7 [#58](https://github.com/joomlatools/joomlatools-console/issues/58)

* 1.4.8 (2017-02-08)
    * Fixed - Update paths in `site:token` command [#57](https://github.com/joomlatools/joomlatools-console/issues/57)
    * Fixed - Update Joomlatools components and framework installers [#57](https://github.com/joomlatools/joomlatools-console/issues/57)

* 1.4.7 (2017-01-13)
    * Added - `site:list` command with JSON output [#17](https://github.com/joomlatools/joomlatools-console/issues/17)
    * Fixed - Support recent versions of Composer in `plugin:install` [#41](https://github.com/joomlatools/joomlatools-console/issues/41)
    * Fixed - Autoload path for custom composer vendor-dir [#44](https://github.com/joomlatools/joomlatools-console/issues/44)
    * Fixed - Allow version 'none' to pass branch check [#11](https://github.com/joomlatools/joomlatools-console/issues/11)

* 1.4.6 (2016-04-01)
    * Fixed - Media folder handling in Joomlatools Framework component symlinking script

* 1.4.5 (2016-03-23)
    * Added - Support for different folder structures for Joomlatools Framework components

* 1.4.4 (2015-11-30)
    * Added - Add `--mysql-port` configuration option (#8)
    * Improved - Renamed package to `joomlatools/console`, moved repository to `joomlatools/joomlatools-console`.
    * Improved - Renamed `joomlatools/joomla-platform` package to `joomlatools/platform`.

* 1.4.3 (2015-11-25)
    * Added - Support [Joomlatools Platform](http://github.com/joomlatools/joomlatools-platform) release tags
    * Improved - Prevent creation of empty site when downloading Joomla codebase fails
    * Improved - Verify list of branches and tags and throw exception if failed to update
    * Fixed - `site:create` documentation mentioned `--joomla` flag instead of `--release`
    * Fixed - Reading version number from Joomla CMS 3.5.x was broken due to changes in `JVersion` class

* 1.4.2 (2015-11-05)
    * Added - `--http-port` flag to `site:create` command
    * Improved - Command help descriptions
    * Fixed - `site:token` used hardcoded paths

* 1.4.1 (2015-10-06)
    * Added - Ensure `extension:symlink` can properly symlink nooku-component repositories
    * Improved - Throw error and exit execution if symlink cannot find the given element
    * Improved - Increased verbosity for the `extension:symlink` and `extension:install` commands when using the `-v` flag.
    * Removed - `symlink` command

* 1.4.0 (2015-09-01)
    * Added - `site:deploy`: deploy command to push sites using [git-ftp](https://github.com/git-ftp/git-ftp)
    * Added - `cache:list`, `cache:purge`, `cache:clear`: commands to handle cache manipulation in Joomla sites
    * Added - `site:checkin`: check-in locked rows
    * Added - `extension:uninstall`: Uninstall command to remove extensions
    * Added - `finder:index`, `finder:purge`: commands to build and purge com_finder indexes
    * Added - `extension:enable`, `extension:disable`: ability to enable and/or disable extensions
    * Added - Interactive mode (prompts for configuration details)
    * Improved - Implemented support for [Joomlatools Platform](http://github.com/joomlatools/joomlatools-platform)
    * Improved - Decoupled the various installation steps into separate commands [#5](https://github.com/joomlatools/joomlatools-console/issues/5)
    * Improved - Symlinker logic is now extendible using plugins.
    * Improved - Ability to install from a different repository instead of the joomla/joomla-cms
    * Improved - Renamed the --joomla version flag to --release
    * Improved - It is now possible to symlink all available projects in the Projects directory at once using _all_ as argument
    * Improved - Install all discovered extensions in one command using the _all_ argument
    * Fixed - Documentroot in virtual hosts now works for non-default --www arguments
    * Fixed - Improved Joomla bootstrapper, add client_id as configuration option


* 1.3.3 (2015-07-30)
    * Fixed - Update dependency names in symlink command

* 1.3.2 (2015-05-12)
    * Improved - Updated installation instructions and README
    * Added - Allow custom toplevel namespace for plugins
    * Improved - Load all plugin classes before instantiating custom commands
    * Fixed - Search for stable versions by default when installing plugins instead of defaulting to dev-master

* 1.3.1 (2015-05-07)
    * Fix the missing extension:register command.

* 1.3.0 (2015-05-05)
    * New plugin system to easily extend Joomla console with new commands
    * New extension:register command to register components to extensions table for development
    * Add SSL support to the virtual hosts created by site:create command
    * Show default creations after site:create command
    * Fix Joomla 2.5 installation issues
    * Fix site:delete issue when it's called from the site folder
    * Fİx latest version check

* 1.2.0 (2014-11-06)
    * Added - redirect() stub to Joomla Application class
    * Added - Try/catch block in Joomla extension installer
    * Added - Symlinking support for Koowa components
    * Fixed - Use LF endings for bin/joomla
    * Fixed - Database creation with a dash in the name

* 1.1.0 (2014-07-09)
    * Improved - Cleary indicate default login credentials
    * Added - Created `extension:installfile` command to install directly from directory/package
    * Added - Implemented `site:token` command to generate JWT tokens
    * Added - Expose `--clear-cache` flag to purge the downloaded files cache

* 1.0.0 (2014-03-12)
    * Added - Initial release
