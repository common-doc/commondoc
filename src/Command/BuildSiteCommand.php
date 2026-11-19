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

namespace CommonDoc\Command;

use CommonDoc\Exception\InvalidArgumentException;
use CommonDoc\Input\Root;
use Override;
use Webware\MessageBus\Command\NamedCommandInterface;

/**
 * Bus command asking for the site to be built.
 *
 * It carries what this run does, not what the deployment is: the roots and the
 * output directory, which are the only two things a command line may override.
 * Everything else is constant for a site and is read from configuration before the
 * command exists.
 *
 * The roots are a list because a site may be composed from more than one
 * repository. Each is mounted at a prefix, and the prefix is the whole mechanism
 * that turns several repositories into one address space.
 *
 * Immutable, so a phase that needs to hand something forward returns a new
 * instance rather than writing to this one. `PipelineHandlerInterface::handle()`
 * takes a message, so that is the channel a phase uses.
 *
 * Implements NamedCommandInterface, which extends CommandInterface: the guard
 * requires the named form of every class in a `Command\` namespace.
 *
 * @api
 */
final readonly class BuildSiteCommand implements NamedCommandInterface
{
    /**
     * @param list<Root> $roots  Where the sources are, and where each is published
     * @param string     $output The directory the rendered site is written to
     *
     * @throws InvalidArgumentException When no root is supplied
     */
    public function __construct(
        private array $roots,
        private string $output,
    ) {
        if ([] === $roots) {
            throw new InvalidArgumentException(
                'A build request needs at least one source root.',
            );
        }
    }

    #[Override]
    public function getCommandName(): string
    {
        return self::class;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * The roots this request builds, in the order they were configured.
     *
     * @return list<Root>
     */
    public function getRoots(): array
    {
        return $this->roots;
    }

    /**
     * The same request, written somewhere else.
     *
     * @throws InvalidArgumentException When the directory is empty
     */
    public function withOutput(string $output): self
    {
        if ('' === $output) {
            throw new InvalidArgumentException('A build request needs an output directory.');
        }

        return new self(
            roots : $this->roots,
            output: $output,
        );
    }

    /**
     * The same request, over different roots.
     *
     * @param list<Root> $roots
     *
     * @throws InvalidArgumentException When no root is supplied
     */
    public function withRoots(array $roots): self
    {
        return new self(
            roots : $roots,
            output: $this->output,
        );
    }
}
