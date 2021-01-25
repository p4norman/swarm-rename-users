<?php
/**
 * Perforce Swarm
 *
 * @copyright   2019 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 */

namespace RenameUser\Listener;

use Application\Log\SwarmLogger;
use Events\Listener\AbstractEventListener;
use Laminas\EventManager\Event;
use RenameUser\RenameUsers;

/**
 * Listener class to handle RenameUser events from the trigger
 * @package RenameUser\Listener
 */
class RenameUserListener extends AbstractEventListener
{
    public function handleUserRename(Event $event)
    {
        // trigger_error("RenameUserListener", E_USER_NOTICE);

        $p4admin = $this->services->get('p4_admin');
        $logger = $this->services->get(SwarmLogger::SERVICE);
        $name = $event->getName();
        $data = $event->getParam('data');
        $logger->trace(self::class . " processing event $name");

        $renamer = $this->services->get(RenameUsers::RENAME);

        try {
            // We define all the username changes here in an array
            // [ 'old_name' => 'new_name', ...]

            $changedUsers = array();

            foreach ($data as $entry) {
                $changedUsers[$entry['from']] = $entry['to'];
            }
            $logger->debug(self::class . " renaming user:  " . var_export($changedUsers, true));
            $renamer->rename($changedUsers);
            $logger->trace(self::class . " Finished processing event $name");
        } catch (\Exception $e) {
            $logger->err($e);
        }
    }
}

