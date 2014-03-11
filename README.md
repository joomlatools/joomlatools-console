Joomla Command Line Tools
=========================

This is a script developed by Joomlatools to ease the management of Joomla sites in [Joomlatools vagrant box.](https://github.com/joomlatools/joomla-vagrant)

For available options, try running:

    joomla --list

Create Sites
------------

To create a site with the latest Joomla version, run:

    joomla site:create testsite

Add the following line into your /etc/hosts file on your host machine:

    33.33.33.58 testsite.dev

The newly installed site will be available at testsite.dev after that.

You can choose the Joomla version or the sample data to be installed:

    joomla site:create testsite --joomla=2.5 --sample-data=blog

You can pick any branch from the Git repository (e.g. master) or any version from 2.5.0 and up using this command.

You can also add your projects into the new site by symlinking. See the Symlinking section below for detailed information.

    joomla site:create testsite --symlink=project1,project2

For more information and available options, try running:

    joomla site:create --help

Delete Sites
------------

You can delete the sites you have created by running:

    joomla site:delete testsite

Symlink your code into a Joomla installation
--------------------------------------------

Let's say you are working on your own Joomla component called _Awesome_ and want to continue working on it using the Vagrant box.

If your source code is located at _/Users/myname/Projects/awesome_, we should start by making this directory available to the Vagrant box. Please note that your source code should resemble the Joomla folder structure, for example your administrator section should reside in _/Users/myname/Projects/awesome/administrator/components/com_awesome_.

Copy the ```config.custom.yaml-dist``` file to ```config.custom.yaml``` and edit with your favorite text editor. Make it look like this:

    synced_folders:
      /home/vagrant/Projects: /Users/myname/Projects

Save this file and restart the Vagrant box. (```vagrant reload```)

The "Projects" folder from your host machine will now be available inside the Vagrant box through _/home/vagrant/Projects_.

Now to create a new site, execute the site:create command and add a symlink option:

	  joomla site:create testsite --symlink=awesome

Or to symlink your code into an existing site:

    joomla site:symlink testsite awesome

This will symlink all the folders from the _awesome_ folder into _testsite.dev_. Run discover install to make your component available to Joomla and you are good to go!

For more information on the symlinker, run:

	  joomla site:symlink  --help


