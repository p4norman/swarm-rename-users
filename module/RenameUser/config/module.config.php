<?php
/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2020 Perforce Software. All rights reserved
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace RenameUser;

use Application\Factory\InvokableServiceFactory;
use Events\Listener\ListenerFactory as EventListenerFactory;
use RenameUser\Factory\ConsoleApplicationFactory;
use Symfony\Component\Console\Application as ConsoleApplication;

$listeners = [Listener\RenameUserListener::class];
return [
    'listeners' => $listeners,
    'service_manager' => [
        'aliases' => [
            RenameUsers::RENAME => RenameUsers::class,
            RenameUsers::CONSOLE => ConsoleApplication::class,
        ],
        'factories' => [
            Listener\RenameUserListener::class => EventListenerFactory::class,
            RenameUsers::class => InvokableServiceFactory::class,
            ConsoleApplication::class => ConsoleApplicationFactory::class,
        ],
    ],
    EventListenerFactory::EVENT_LISTENER_CONFIG => [
        'task.userrename' => [
            Listener\RenameUserListener::class => [
                [
                    EventListenerFactory::PRIORITY        => EventListenerFactory::DEFAULT_PRIORITY,
                    EventListenerFactory::CALLBACK        => 'handleUserRename',
                    EventListenerFactory::MANAGER_CONTEXT => 'queue',
                ]
            ]
        ]
    ],
];
