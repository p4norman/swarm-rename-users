<?php
namespace RenameUser\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Symfony\Component\Console\Application as ConsoleApplication;
use RenameUser\Commands\RenameUsersCommand;


class ConsoleApplicationFactory implements FactoryInterface
{
    const APP_NAME = 'RenameUser';
    const APP_VERSION = '2020.1';

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $application = new ConsoleApplication(self::APP_NAME, self::APP_VERSION);
        $application->add(new RenameUsersCommand($container));

        return $application;
    }

}