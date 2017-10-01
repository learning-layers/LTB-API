LTB-API
=======

== INSTALLATION ==
For an independent server (not using the Layersbox and docker-compose), follow 
these installation instructions:

Run in a console:
(Note: if you do not have composer.phar installed -it is included in this git for now-
you can get it by typing in your cloned repo dir: curl -sS https://getcomposer.org/installer | php.
this will put composer.phar into your current directory as composer.phar.)
 php composer.phar selfupdate
 php composer.phar install
 php composer.phar update

This will retrieve the vendor libraries and dependencies

Create a database on the same server and create a user for it
Now copy the instance.php.dist to instance.php and modify the settings with the 
location and database details as valid for your situation.

=== RELEASE NOTES ===
Version 0.7.1: From April 2016
    Added features:
        -A new service Reference to keep track of links and files. These are stored as an entry in the database and files are stored locally on the server
        -We have added a new service to serve the file for authenticated users. So files can be downloaded or used in an image tag
        -Logging users requests in the database.
	 -We have added some media players in the Tilestore/App to be able to play various media files: images, pdf, audio and video
    Soon to come:
        -Reference reuse: refer to other references
        -Sharing policy: introducing groups to share content with and hide public materials (i.e. serve a group of users by distributing the unique link only to them; the items are not shown in search actions)
        -Support for Docker setup
        -Full Social Semantic Server support
