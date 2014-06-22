=== Revisr ===
Contributors: ExpandedFronts
Tags: revisr, git, git management, revision tracking, revision, backup, deploy, commit, bitbucket, github
Requires at least: 3.5.1
Tested up to: 3.9.1
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

A simple plugin that integrates your git repository with WordPress.

== Description ==

Revisr allows you to manage your WordPress website with a git repository. With Revisr, you can:

* Track changes to the files of your WordPress installation
* Commit and push changes to a remote repository (including Bitbucket and Github)
* Pull changes down from a remote repository
* Easily toggle between branches
* Revert to an earlier commit
* Discard any unwanted changes
* Manage .gitignore to prevent unwanted files/directories from being tracked

A must have plugin for deploying WordPress using git repositories.

*Git Logo by Jason Long is licensed under the Creative Commons Attribution 3.0 Unported License.*

== Installation ==

= Requirements = 
* A WordPress installation in the root folder of a Git repository
* Git must be installed on the server (most updated versions of cPanel have it preinstalled)
* PHP exec (safe mode off, can be configured in your php.ini)

= Instructions =
* Unzip the plugin folder and upload it to the plugins directory of your WordPress installation.
* Configure any remote repositories on the plugin settings page. Supports git through SSH or HTTPS. 
* If the repository was cloned from a remote, Revisr will attempt to use the settings stored in Git.

It is also adviseable to add Revisr to the gitignore file via the settings page to make sure that reverts don't rollback the plugins' functionality. 

== Frequently Asked Questions ==

= Why are my commits timing out? =
This is likely an authentication issue. You can fix this by configuring your SSH keys or using the HTTPS authentication option on the settings page.

= Why aren't my commits being pushed to the remote repository? =
This is either an authentication issue or the remote branch is ahead of yours.

= Can I damage my site with this plugin? =
Yes, care should be taken when dealing with upgrades that depend on the database. For example, upgrading to the next major version of WordPress and later reverting could cause issues if there are significant changes to the database. 

== Screenshots ==

1. The main dashboard of revisr.
2. Easily view changes in files.
3. The commit history.
4. Git settings and options.


== Changelog ==

= 1.2.1 =
* Minor bugfixes

= 1.2 =
* Added ability to view the number of pending files in the admin bar
* Small cleanup, updated wording

= 1.1 =
* Bugfixes and improvements
* Added ability to view changes in files from a previous commit

= 1.0.2 =
* Minor bugfixes

= 1.0.1 =
* Updated readme.txt

= 1.0 =
* Initial commit