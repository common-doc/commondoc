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
use CommonDoc\Exception\ExceptionInterface;
use CommonDoc\Input\Root;
use CommonDoc\Record\BuildRecord;
use CommonDoc\SiteConfig;
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
 * With no arguments it builds the configured site, which is the common case: a
 * workflow runs this from configuration alone, so anything it had to pass would
 * have moved the site definition out of the repository and into workflow YAML.
 *
 * The two options exist for development. `--source` replaces the configured roots
 * with one root published at `/`, which is useful against a single repository and
 * is not a way to compose a site; `--output` replaces the configured destination.
 * Neither can express a mount, because a mount is part of what the site is and is
 * read from configuration.
 *
 * The result is narrowed to a `BuildRecord`, and anything else is said out loud:
 * filtering the result for strings once meant an empty result printed nothing, so
 * a silent no-op looked like a successful build.
 *
 * The exit code follows the record rather than the status, because the record is
 * what was actually written. A handler that reported success while recording a
 * failure would otherwise exit zero with failures printed, and the record is the
 * only place those two facts can be compared.
 *
 * @api
 */
#[AsCommand(name: self::NAME, description: 'Build the documentation site from its configured sources')]
final class BuildCommand extends Command
{
    public const string NAME = 'commondoc:build';

    /**
     * @throws ConsoleExceptionInterface
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SiteConfig $site,
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
            mode       : InputArgument::OPTIONAL,
            description: 'Build this directory alone, published at "/", instead of the configured roots.',
        );

        $this->addOption(
            name       : 'output',
            shortcut   : 'o',
            mode       : InputOption::VALUE_REQUIRED,
            description: 'Write the site here instead of the configured output directory.',
        );
    }

    /**
     * @throws ConsoleExceptionInterface From the console input, which validates
     *         its own types before this method sees them. It is a distinct
     *         hierarchy, so it has to be named: this package's exception is
     *         final and cannot cover another library's class.
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->bus->handle($this->request($input));
        } catch (ExceptionInterface $refusal) {
            // Rendered rather than thrown. The interactive menu reads a command's
            // exit code and does not catch, so an exception here would tear down
            // the session instead of naming what was wrong with the request.
            $output->writeln(sprintf('<error>%s</error>', $refusal->getMessage()));

            return Command::INVALID;
        }

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

        foreach ($raw->output as $path) {
            $output->writeln(sprintf('Wrote %s', $path));
        }

        foreach ($raw->failed as $path => $reason) {
            $output->writeln(sprintf('<error>Failed %s: %s</error>', $path, $reason));
        }

        return $raw->complete() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * The request this run makes: the configured site, with the two overrides
     * applied when they were given.
     *
     * @throws ConsoleExceptionInterface From the console input, which validates its
     *         own types before this method sees them
     * @throws ExceptionInterface When the request or an override cannot be honoured
     */
    private function request(InputInterface $input): BuildSiteCommand
    {
        $request = new BuildSiteCommand(
            roots : $this->site->roots,
            output: $this->site->output,
        );

        /** @var mixed $source */
        $source = $input->getArgument('source');

        if (is_string($source) && '' !== $source) {
            $request = $request->withRoots([new Root(path: $source)]);
        }

        /** @var mixed $target */
        $target = $input->getOption('output');

        if (is_string($target) && '' !== $target) {
            $request = $request->withOutput($target);
        }

        return $request;
    }
}
