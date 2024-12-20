<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use whikloj\BagItTools\Bag;
use whikloj\BagItTools\BagUtils;
use whikloj\BagItTools\Exceptions\BagItException;

/**
 * Command to validate a bag.
 * @package whikloj\BagItTools\Commands
 */
class ValidateCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('validate')
            ->setDescription('Validate a BagIt bag.')
            ->setHelp("Point at a bag file or directory, increase verbosity for more information.")
            ->addArgument(
                'bag-path',
                InputArgument::REQUIRED,
                'Path to the bag directory or file'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $path = BagUtils::standardizePathSeparators($input->getArgument('bag-path'));
        if (($path[0] ?? "") !== '/' && !preg_match("/^[a-z]:/i", $path)) {
            $root_path = getcwd();
            if ($root_path === false) {
                $io->error("Failed to get current working directory.");
                return(1);
            }
            $path = "$root_path/$path";
            $realpath = realpath($path);
        }
        if ((isset($realpath) && is_bool($realpath)) || !file_exists($path)) {
            $io->error("Path $path does not exist, cannot validate.");
        } else {
            try {
                if (isset($realpath) && is_string($realpath)) {
                    $path = $realpath;
                }
                $bag = Bag::load($path);
                $valid = $bag->isValid();
                $verbose = $output->getVerbosity();
                if ($verbose >= OutputInterface::VERBOSITY_VERBOSE) {
                    // Print warnings
                    $warnings = $bag->getWarnings();
                    foreach ($warnings as $warning) {
                        $io->warning("{$warning['message']} -- file: {$warning['file']}");
                    }
                }
                if ($verbose >= OutputInterface::VERBOSITY_VERBOSE) {
                    // Print errors
                    $errors = $bag->getErrors();
                    foreach ($errors as $error) {
                        $io->error("{$error['message']} -- file: {$error['file']}");
                    }
                }
                if ($verbose >= OutputInterface::VERBOSITY_NORMAL) {
                    if ($valid) {
                        $io->success("Bag is valid");
                    } else {
                        $io->warning("Bag is NOT valid");
                    }
                }
                return($valid ? 0 : 1);
            } catch (BagItException $e) {
                $io->error("Exception: {$e->getMessage()}");
            }
        }
        return(1);
    }
}
