<?php

declare(strict_types=1);

/**
 * This file is part of the commondoc package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CommonDoc\Console;

use CommonDoc\Command\BuildSiteCommand;
use CommonDoc\Record\BuildRecord;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webware\MessageBus\MessageBusInterface;

use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * The CLI surface of the site builder.
 *
 * Dispatches a BuildSiteCommand on the bus and prints what the handler reports.
 * The result is narrowed to a `BuildRecord`, and anything else is said out loud:
 * the previous version filtered the result for strings, so an empty result
 * printed nothing and a silent no-op looked like a successful build.
 *
 * The exit code follows the record rather than the status, because the record is
 * what was actually written. A handler that reported success while recording a
 * failure would otherwise exit zero with failures printed, and the record is the
 * only place those two facts can be compared.
 *
 * @api
 */
#[AsCommand(name: self::NAME, description: 'Build the documentation site from a directory of Markdown sources')]
final class BuildCommand extends Command
{
    public const string NAME = 'commondoc:build';

    /**
     * @throws ConsoleExceptionInterface
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    /**
     * @throws ConsoleExceptionInterface
     */
    #[Override]
    protected function configure(): void
    {
        $this->addArgument(
            name       : 'source',
            mode       : InputArgument::REQUIRED,
            description: 'Directory holding the Markdown sources.',
        );

        $this->addOption(
            name       : 'output',
            shortcut   : 'o',
            mode       : InputOption::VALUE_REQUIRED,
            description: 'Directory the rendered site is written to.',
            default    : 'build',
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var mixed $source */
        $source = $input->getArgument('source');

        /** @var mixed $target */
        $target = $input->getOption('output');

        if (! is_string($source) || '' === $source) {
            throw new InvalidArgumentException('The "source" argument must be a non-empty string.');
        }

        if (! is_string($target) || '' === $target) {
            throw new InvalidArgumentException('The "--output" option must be a non-empty string.');
        }

        $result = $this->bus->handle(new BuildSiteCommand(
            source: $source,
            output: $target,
        ));

        /** @var mixed $raw */
        $raw = $result->getResult();

        if (! $raw instanceof BuildRecord) {
            $output->writeln(sprintf(
                '<error>The build returned %s instead of a %s, so nothing can be reported.</error>',
                get_debug_type($raw),
                BuildRecord::class,
            ));

            return Command::FAILURE;
        }

        foreach ($raw->output as $document) {
            $output->writeln(sprintf('Wrote %s', $document));
        }

        foreach ($raw->failed as $document => $reason) {
            $output->writeln(sprintf('<error>Failed %s: %s</error>', $document, $reason));
        }

        return $raw->complete() ? Command::SUCCESS : Command::FAILURE;
    }
}
