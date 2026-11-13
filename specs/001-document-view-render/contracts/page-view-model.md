# Contract: Page as a view model

**Type**: Public surface. Every template, helper and caller that receives a page consumes this
contract.

**Implements**: `Laminas\View\Model\ModelInterface` (which extends `Countable` and
`IteratorAggregate<int, ModelInterface>`).

## Read side

| Method | Signature | Behaviour |
|---|---|---|
| `getTemplate()` | `(): string` | Returns the template name |
| `getVariables()` | `(): array<string, mixed>` | Returns the typed projection of the entity fields |
| `getVariable($name, $default = null)` | `(string $name, mixed $default = null): mixed` | Returns the projection entry, or `$default` when absent |
| `getChildren()` | `(): array` | Returns the `list<Page>` |
| `hasChildren()` | `(): bool` | True when the list is non-empty |
| `captureTo()` | `(): string` | Returns the capture name, default `page` |
| `isAppend()` | `(): bool` | Returns the append flag, default false |
| `terminate()` | `(): bool` | Returns the terminal flag, default false |
| `count()` | `(): int` | Number of children |
| `getIterator()` | `(): Traversable` | Iterates the children |

## Write side

Every mutating method of `ModelInterface` throws `LogicException`. The message names immutability
and the page label so a stack trace identifies the offending page.

| Method | Throws |
|---|---|
| `setVariable($name, $value)` | `LogicException` |
| `setVariables($variables, $overwrite = false)` | `LogicException` |
| `setTemplate($template)` | `LogicException` |
| `addChild($child, $captureTo = null, $append = null)` | `LogicException` |
| `setCaptureTo($captureTo)` | `LogicException` |
| `setTerminal($terminal)` | `LogicException` |
| `setAppend($append)` | `LogicException` |

## Guarantees

- The class is `final` and `readonly`. There are no properties outside the constructor promotion.
- There is no `__get`, no `__set`, no `__call`, and no accessor that returns by magic name.
- The variables projection contains no `content` key.
- `count()` and `getIterator()` agree: `count($page) === iterator_count($page->getIterator())`.
- Repeating any read returns the same value for the lifetime of the object.

## Consumers

- Templates receive a page as the `page` variable and read it through the read side.
- Callers build pages with named arguments and never receive a mutated page back.
- The view layer never receives a page as a render root or as an attached child model, because the
  framework writes to both. See [document-renderer.md](./document-renderer.md) and
  [../research.md](../research.md) D6.

## Verification

Contract verification belongs in `test/unit/`: a type-conformance test asserting the class is an
instance of the interface, plus behaviour tests for each read and each thrown exception. A contract
test is a unit test, not an integration test.
