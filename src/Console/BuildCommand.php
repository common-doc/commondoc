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
use Webware\MessageBus\MessageStatus;

use function array_filter;
use function is_array;
use function is_string;
use function sprintf;

/**
 * The CLI surface of the site builder.
 *
 * Dispatches a BuildSiteCommand on the bus and maps the status the handler
 * returns straight to the exit code. Only string entries in the result are
 * printed, so an empty result prints nothing.
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

        $status = $result->getStatus();

        /** @var mixed $raw */
        $raw = $result->getResult();

        $messages = is_array($raw) ? array_filter($raw, is_string(...)) : [];

        foreach ($messages as $message) {
            $output->writeln(
                MessageStatus::Success === $status
                    ? sprintf('Wrote %s', $message)
                    : sprintf('<error>%s</error>', $message),
            );
        }

        return MessageStatus::Success === $status ? Command::SUCCESS : Command::FAILURE;
    }
}
