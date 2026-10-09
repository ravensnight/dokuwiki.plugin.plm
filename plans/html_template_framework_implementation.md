# Plan: HTML Template Framework Implementation (Option 2 Hybrid)

## Objective
Implement the `HtmlBakery`, `Renderer`, and `DefaultRenderer` components using a hybrid approach: a Renderer Registry with Reflection fallback.

## Architecture
- **HtmlBakery:** Parses the XML receipt, replaces `{<name>}` context variables in attributes, and initiates the rendering process.
- **Renderer (Abstract):** Handles the common logic of extracting `plm:` attributes, writing the start tag, iterating sub-tags, and writing the closing tag.
- **DefaultRenderer:** Implements the standard rendering behavior for all tags.
- **Renderer Registry:** A singleton/static registry that caches instantiated Renderers. If a renderer is requested via `plm:renderer` and is not in the registry, it is instantiated via Reflection and cached.

## Implementation Details
1. **HtmlBakery::parse($writer, $receipt, $context)**
    - Load XML.
    - Recursive traversal.
    - Attribute replacement using `preg_replace_callback` for `{<name>}` patterns.

2. **Renderer Logic**
    - Strip attributes starting with `plm:`.
    - Identify the correct renderer class.
    - If `plm:renderer` is absent, use `DefaultRenderer`.
    - If `plm:renderer` is present, check the Registry. If missing, use Reflection to instantiate.

## Files to be created/modified
- `lib/plugins/plm/service/ui/HtmlBakery.php`
- `lib/plugins/plm/service/ui/Renderer.php`
- `lib/plugins/plm/service/ui/DefaultRenderer.php`
- `lib/plugins/plm/service/ui/RendererRegistry.php` (New)

## Decision Support
- **Option Selected:** Hybrid Registry + Reflection.
- **Pros:** Better performance than pure reflection while maintaining the flexibility requested in the README.
- **Cons:** Slightly more complex logic in the Factory/Registry.
