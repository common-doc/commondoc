# Contract: PageContext and its listener

**Type**: Cross-component boundary between the build loop, `league/commonmark` events and this
package's renderer.

## PageContext

| Method | Signature | Behaviour |
|---|---|---|
| `set` | `(Page $page): void` | Writes the single slot |
| `clear` | `(): void` | Empties the slot; safe to call when already empty |
| `current` | `(): Page|null` | The slot, or null |

**Rules**

- Exactly one page may be held. `set` replaces; the class does not reject a replace, because a
  replace indicates a caller that failed to clear, and hiding that is worse than allowing it.
- No locking, no stack, no queue. One conversion at a time is the caller's contract.
- The class holds no static state, no registry and no service locator.

## PageContextListener

**Handles**: `League\CommonMark\Event\DocumentPreRenderEvent`

**Signature**: `public function __invoke(DocumentPreRenderEvent $event): void`

**Behaviour**

- Reads `PageContext::current()`.
- When the page is not null, writes it to `$event->getDocument()->data` under `commondoc.page`.
- When the page is null, writes nothing. A document that already carries a page keeps it, so a
  document built outside the build loop still renders with its own page.

**Registration**: in `EnvironmentFactory`, on the environment the factory returns, so every
conversion performed by that environment sees the listener.

## Caller obligation

The build loop must bracket every conversion:

```php
$pageContext->set($page);

try {
    $html = $converter->convert($markdown);
} finally {
    $pageContext->clear();
}
```

A conversion that throws must still clear, which is why the clear is in `finally` and not after the
call.

## Guarantees to the renderer

- A page present in `commondoc.page` is the page that was current when the conversion started.
- A missing entry means "no page", which the renderer must tolerate.
- The entry is never a partially populated page, because page construction is complete before `set`.

## Naming constraint

The type is `PageContext`. `PageContextHolder` was drafted and rejected by the owner. No new type in
this feature may carry a `Holder`, `Manager`, `Helper`, `Util` or `Service` suffix.
