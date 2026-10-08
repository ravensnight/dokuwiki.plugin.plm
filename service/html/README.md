# Html Bakery Framework

This is the Html Bakery Framework implementation as specified in PROJECT.md.

## Directory Structure
```
./
├── core/                 # Framework base classes
├── test/                 # Test classes and templates
├── templates/            # Productive JSON templates
├── index.php             # Main entry point
└── PROJECT.md            # Project documentation
```

## Core Components
- HtmlBuilder class (from util directory)
- AbstractRenderer class (base renderer interface)
- DefaultRenderer class (default content rendering)
- DataProvider abstract class (base DataProvider interface)
- TemplateParser class (template parsing functionality)
- HtmlBakery main class (main framework orchestrator)

## Features
- Modular architecture for scalable application development
- Built-in templating engine for dynamic HTML generation from JSON templates
- Support for JSON template structure with root elements being either arrays or tag nodes
- Tag nodes with 'tag', 'attrs', 'content', 'dataProvider', and 'renderer' attributes
- Default renderer that handles both scalar content and array-of-tag-nodes content
- Custom renderer support through 'renderer' attribute
- DataProvider functionality through 'dataProvider' attribute