CHANGELOG
=========

This changelog references the relevant changes (bug and security fixes) done
in 1.x versions.

To get the diff for a specific change, go to https://github.com/joomlatools/joomla-console/commit/xxx where xxx is the change hash.
To view the diff between two versions, go to https://github.com/joomlatools/joomla-console/compare/v1.0.0...v1.0.1

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
