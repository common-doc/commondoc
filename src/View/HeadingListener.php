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

namespace CommonDoc\View;

use CommonDoc\Exception\InvalidArgumentException;
use Dflydev\DotAccessData\Exception\InvalidPathException;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading as HeadingNode;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;

use function is_string;
use function trim;

/**
 * Collects a document's headings as link targets, for whatever builds links to them.
 *
 * It runs on the parsed event below the heading permalink extension, because that
 * extension is what assigns a heading's id and it runs at a priority of `-100`. A
 * listener at the default priority would run first and find no ids, so the priority is
 * registered explicitly rather than left to addition order.
 *
 * The id is read from where the renderer puts it, `attributes/id` in the heading's data
 * bag, which is the same value the renderer writes to the `id` attribute of the `<h2>`.
 * Reading what the renderer emits is what makes a fragment reach its heading, rather than
 * a second translation of the same text that could drift.
 *
 * A heading with no id is skipped rather than refused. An id comes from an extension, and
 * a deployment without the permalink extension still builds a valid site; it simply has
 * no sections to link to. Refusing there would turn a rendering choice into a failed
 * build.
 *
 * The label is the heading's text, not its HTML. A heading containing emphasis or a link
 * reduces to the words it shows, because a link is labelled with text and an anchor
 * inside an anchor is not possible.
 */
final readonly class HeadingListener
{
    /**
     * Where a heading's id lives in its data bag, which is the key the renderer reads.
     */
    private const string ID_KEY = 'attributes/id';

    /**
     * Runs after the extension that assigns ids.
     *
     * Lower than `-100`, because a listener at equal priority would be ordered by
     * registration and this one is registered after the extensions.
     */
    public const int PRIORITY = -200;

    public function __construct(
        private PageContext $context,
    ) {}

    /**
     * The text a reader sees, with descendant markup reduced to its words.
     *
     * CommonMark's own helper, which is the same reduction the heading permalink extension
     * applies when it derives the slug. That is deliberate: the label and the slug then come
     * from one traversal rule, so a heading whose slug says `set-and-links` is not labelled
     * `and links` because a code span was skipped.
     *
     * Raw markup is excluded, because it is not text a reader sees.
     */
    private static function label(HeadingNode $heading): string
    {
        return trim(StringContainerHelper::getChildText(
            node        : $heading,
            excludeTypes: [RawMarkupContainerInterface::class],
        ));
    }

    /**
     * The heading this node is, or null when it is not a linkable heading.
     *
     * @throws InvalidArgumentException When the heading's level is outside 1 to 6, which the
     *                                  renderer cannot produce and a constructed heading can
     * @throws InvalidPathException When the heading's data cannot be read, which a node the
     *                              renderer built never does
     */
    private function one(Node $node): ?Heading
    {
        if (! $node instanceof HeadingNode) {
            return null;
        }

        if (! $node->data->has(self::ID_KEY)) {
            return null;
        }

        /** @var mixed $id Deliberately untyped: the check below decides what it is */
        $id = $node->data->get(self::ID_KEY);

        if (! is_string($id) || '' === $id) {
            return null;
        }

        return new Heading(
            level   : $node->getLevel(),
            label   : self::label($node),
            fragment: $id,
        );
    }

    /**
     * @throws InvalidArgumentException When a heading's level is outside 1 to 6
     * @throws InvalidPathException When a heading's data cannot be read
     */
    public function __invoke(DocumentParsedEvent $event): void
    {
        $headings = [];

        foreach ($event->getDocument()->iterator() as $node) {
            $heading = $this->one($node);

            if (null !== $heading) {
                $headings[] = $heading;
            }
        }

        $this->context->setSections($headings);
    }
}
