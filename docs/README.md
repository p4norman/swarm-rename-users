[![Support](https://img.shields.io/badge/Support-Community-yellow.svg)]
# Swarm RenameUsers module for 2019.3

## Overview:  
This module extends Helix Swarm to support the renaming of Perforce users in two different ways:
1. It adds a command line interface to swarm allowing the batch renaming of many users.
2. It includes a trigger which listens for the command `p4 renameuser` and then updates swarm automatically as users are renamed.

## Requirements

1. This module will extend Swarm 2019.3, other versions of swarm will probably not work.  
2. Swarm 2019.3 should already be installed and working.
2. This module installation requires that "composer" 1.X be used, newer versions will not work.
3. The trigger has the same requirements as the existing swarm perl based triggers.

## Support

This project is a community supported project and is not officially supported by Perforce.  
Pull requests and issues are the responsibility of the project's moderator(s);  
Perforce does not officially support this project, therefore all issues should be reported and managed via GitHub. 

## Installation

1. Swarm data is stored in Helix Perforce tables, 
therefore, before a batch rename, please create and save a checkpoint of your server.
2. Identify your $SWARMROOT, in most installations it is `/opt/perforce/swarm`.
3. Make sure that you have installed version 1.X of composer. ( not the latest release ) 
   This can be downloaded from the "Manual Download" section of https://getcomposer.org/download
4. Change to the $SWARMROOT directory.   
5. Make sure that composer.json and composer.lock are writable:     
   `sudo chmod 666 composer.*`    
6. Extract this modules' files into your $SWARMROOT, overwriting composer.json and composer.lock   
   `sudo tar xvfz renameusermodule.tgz`
7. Download additional files needed by this module:   
   `composer install`
8. Clear the config cache:  
   `sudo rm -rf data/cache/*`
   
### Trigger Installation

1. The trigger is in `$SWARMROOT/p4-bin/scripts/rename-swarm-trigger.pl`
2. Customize the configuration in `rename-swarm-trigger.pl`, or you can share the same trigger configuration file as used by the existing swarm-trigger.pl
<https://www.perforce.com/manuals/v19.3/swarm/Content/Swarm/setup.perforce.html#Helix_Core_server_configuration_for_Swarm>

3. You must copy the trigger file to your Perforce server, then (as a Perforce super user) update the trigger table using `"p4 triggers"`  
by adding this trigger line:  
   	`swarm.renameuser command post-user-renameuser "%quote%rename-swarm-trigger.pl%quote% -t userrename -v %argc% -x %maxErrorSeverity% -a %quote%%args%%quote%'"`

## Using batch rename

1. `$SWARMROOT/users.php` contains a table of all swarm users to be renamed.
This file must be customized before running the renameuser command.
       
       <?php
       return array(
           'betty' => 'boop',
           'oldname' => 'newname',
       ); 
2. Run renameusers in preview mode (the default) and with logging enabled    
   `$SWARMROOT/bin/console renameusers -l`
    This will create the log file `$SWARMROOT/rename.log`
3. View `rename.log` and  make sure the preview run worked correctly.  
The preview will issue WARNINGS if a target username is not an existing Perforce user.
This is to assist administrators in detecting typos in users.php.
 WARNINGS only show up in preview mode, and will not cause the actual renaming to fail.
4. Once the preview runs as expected, run the command again with the "-Y" command to actually make the changes
      `$SWARMROOT/bin/console renameusers -l -Y`
      
## Debugging
1. When the trigger fires, it will write a DEBUG (level 7) log entry to the swarm log, 
   this can only be seen if you set the swarm logging trigger priority to 7 or higher.
   <https://www.perforce.com/manuals/v19.3/swarm/Content/Swarm/quickstart.logging_level.html>
   
2. When using batch rename, run first in preview mode with a log file, this will catch problems early.



   
