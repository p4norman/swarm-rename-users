<?php
namespace RenameUser\Commands;

use Interop\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RenameUser\RenameUsers;

class RenameUsersCommand extends Command
{
    private $services;
    private $logger;
    private $renamer;

    public function __construct(ContainerInterface $services){
        $this->services = $services;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('renameusers')
            ->setDescription('Renames Users')
            ->addOption( 'server', 's', InputOption::VALUE_REQUIRED, "Specify server label for multi p4d swarm")
            ->addOption('log','l',InputOption::VALUE_NONE, "log to rename.log")
            ->addOption( 'confirm', 'y|Y',InputOption::VALUE_NONE, "Must confirm to change data")
            ->setHelp("This command renames the users configured in SWARMROOT/users.php :  [ 'olduser' => 'newname', ... ]");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $infile = BASE_PATH . '/users.php';
        if (! file_exists($infile))
        {
            $output->writeln(sprintf('file %s not found', $infile));
            return;
        }
        $changedUsers = include $infile;

        $renamer = $this->services->get(RenameUsers::RENAME);

        $logging = $input->getOption("log");
        $confirm = $input->getOption("confirm");

        $output->writeln("Reading users to rename from " . $infile );

        $renamer->setLogToFile($logging);
        if ($logging){
            $output->writeln("Writing rename log to " . BASE_PATH . "/data/rename.log");
        }

        $renamer->setPreview(! $confirm);
        if ($confirm){
            $output->writeln("Renaming Users: Changing Swarm Data");
        } else {
            $output->writeln("Renaming Users: Preview Mode");
        }

        if (! $renamer->rename($changedUsers)){
            $output->writeln("ERROR during rename");
        }
    }
}