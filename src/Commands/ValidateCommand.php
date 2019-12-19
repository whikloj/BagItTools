<?php

namespace whikloj\BagItTools\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use whikloj\BagItTools\Bag;
use whikloj\BagItTools\BagItException;

/**
 * Command to validate a bag.
 * @package whikloj\BagItTools\Commands
 */
class ValidateCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('validate')
            ->setDescription('Validate a BagIt bag.')
            ->setHelp("Point at a bag file or directory, increase verbosity for more information.")
            ->addArgument('bag-path', InputArgument::REQUIRED,
                'Path to the bag directory or file');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('bag-path');
        if ($path[0] !== DIRECTORY_SEPARATOR) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
            $realpath = realpath($path);
        }
        if ((isset($realpath) && $realpath === false) || !file_exists($path)) {
            $output->writeln("Path {$path} does not exist, cannot validate.");
        } else {
            try {
                if (isset($realpath) && $realpath !== false) {
                    $path = $realpath;
                }
                $bag = Bag::load($path);
                $valid = $bag->validate();
                $verbose = $output->getVerbosity();
                if ($verbose >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                    // Print warnings
                    $warnings = $bag->getWarnings();
                    foreach ($warnings as $warning) {
                        $output->writeln("Warning: {$warning['message']} -- file: {$warning['file']}");
                    }
                }
                if ($verbose >= OutputInterface::VERBOSITY_VERBOSE) {
                    // Print errors
                    $errors = $bag->getErrors();
                    foreach ($errors as $error) {
                        $output->writeln("Error: {$error['message']} -- file: {$error['file']}");
                    }
                }
                if ($verbose >= OutputInterface::VERBOSITY_NORMAL) {
                    $output->writeln("Bag is" . (!$valid ? " NOT" : "") . " valid");
                }
                exit($valid ? 0 : 1);
            } catch (BagItException $e) {
                $output->writeln("Exception: {$e->getMessage()}");
                exit(1);
            }
        }
    }
}