# Contract: Document node renderer

**Type**: Cross-component boundary between `league/commonmark` and this package.

**Implements**: `League\CommonMark\Renderer\NodeRendererInterface`

**Registered for**: `League\CommonMark\Node\Block\Document::class`, priority 10, in `EnvironmentFactory`.

## Method

```php
public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null;
```

## Preconditions

- `$node` is a `League\CommonMark\Node\Block\Document` instance. Any other type is rejected with
  `InvalidArgumentException`, as the interface requires.
- `$childRenderer` is supplied by the environment and is the only correct source of body output.

## Postconditions

- The returned string is the body template rendered inside the configured layout, with the body
  produced by `$childRenderer->renderNodes($node->children())`.
- `$node` is not mutated, except that the renderer reads `$node->data->get('commondoc.page')`.
- No helper state is retained after the call. The view renderer resets state itself.
- When the document carries no page, the call still returns a rendered string and does not throw.

## Forbidden in an implementation

- Subclassing, replacing or wrapping `HtmlRenderer`, `MarkdownConverter` or
  `MarkdownConverterInterface`.
- Reimplementing child dispatch, for example by inspecting child classes.
- Calling `renderNodes` on the whole document instead of `$node->children()`.
- Writing `content` into the body template's variables.
- Retaining, resetting or caching helper state.

## Testability contract

The seam depends on `Mezzio\Template\TemplateRendererInterface`, not on
`Mezzio\LaminasView\LaminasViewRenderer`, because the latter is `final` in 3.0.0. A unit test
supplies a stub implementing the interface; an integration test supplies the real container.

## Consumer

`CommonDoc\Container\EnvironmentFactory` builds the renderer from the container and registers it:

```php
$environment->addRenderer(Document::class, $renderer, 10);
```
