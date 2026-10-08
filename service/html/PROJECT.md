# Html Bakery Framework

## Project Overview

The Html Bakery Framework is a comprehensive solution for creating, managing, and deploying HTML-based applications and web services. It provides developers with a structured approach to building modern web applications using HTML as the primary interface. The framework specializes in generating HTML content from JSON template files.

## Key Features

- Modular architecture for scalable application development
- Built-in templating engine for dynamic HTML generation from JSON templates
- Support for JSON template structure with root elements being either arrays or tag nodes
- Tag nodes are objects with a 'tag' attribute that defines the HTML tag name to be rendered
- Tag nodes also have an 'attrs' attribute that contains key-value pairs to be rendered as HTML attributes
- Tag nodes can also have a 'content' attribute that defines various content types
- Tag nodes can also have a 'dataProvider' attribute that references a DataProvider class
- Tag nodes can also have a 'renderer' attribute that references a CustomRenderer class
- When the JSON root is an array, the sub-elements are tag nodes
- Content rendering is always performed by a renderer
- If no renderer is explicitly specified in the tag, a default renderer is used
- The default renderer works as follows:
  - If the content is directly defined in the JSON as a simple datatype (string, int, etc.), the value is always rendered as a string into the HTML tag
  - If the content is an array or object, it is interpreted and parsed as a tag node
- Custom renderer support:
  - Can be specified in a tag with the 'renderer' attribute
  - If specified, a class with exactly that name will be instantiated
  - Custom renderers must inherit from an abstract renderer
  - The abstract renderer defines a render function for HTML that always calls the parent node
  - Parameter: 1) $html .. Instance of HtmlBuilder class, created in the root element of HtmlBakery and passed through to renderers. This already exists and should be used from the parent folder ../../util
  - Parameter: 2) $content .. The content from the 'content' attribute, which can also be null if content is not specified
  - Parameter: 3) $data .. An object that can be provided by a parent DataProvider instance. The tag must define an attribute 'dataProvider' that references an inheritance of the abstract class 'DataProvider'
- The renderer knows how to render the passed data. The default renderer ignores the data object.
- DataProvider functionality:
  - A DataProvider creates an object or array of objects that are then rendered by a renderer into HTML
  - If the Provider returns an array of objects, it calls the specified renderer or the DefaultRenderer multiple times until all data objects are rendered
  - If the Provider returns exactly one object, the renderer is called only once
  - An optionally specified 'content' can be used by the renderer to generate additional content
  - To provide objects, the DataProvider defines a 'provide(..)' function that takes a $context parameter
  - The $context parameter is provided by HtmlBakery and passed through a context parameter of the associated 'produce' function
- Framework base classes are located in ./core directory
- Base classes can be deleted and recreated during re-creation processes
- Default implementations that are required are included
- Test classes and test JSON templates are generated in ./test directory
- Productive JSON templates are stored in ./templates directory
- The templates directory and its content are preserved during re-generation
- Special renderers or DataProviders are located in the Framework root directory
- These files are preserved during regeneration
- After framework and tests generation, the tests should be executed
- The result should be an HTML report that demonstrates that the framework works
- The report should summarize all fundamental variants of template definitions together with their respective rendering results
- Integration capabilities with various web technologies
- Support for multiple deployment targets
- Extensible design to accommodate future enhancements

## Project Objectives

1. Provide a robust foundation for HTML-based web development
2. Enable rapid development and deployment of web applications
3. Offer extensible components for custom functionality
4. Maintain high performance and maintainability standards

## Target Audience

- Web developers working with HTML-based applications
- Teams requiring rapid prototyping capabilities
- Organizations seeking structured approaches to web development

## Directory Structure

```
./
├── core/                 # Framework base classes
├── test/                 # Test classes and templates
├── templates/            # Productive JSON templates
├── index.php             # Main entry point
└── PROJECT.md            # Project documentation
```

## Implementation Requirements

1. **Core Components:**
   - HtmlBuilder class (type: HtmlBuilder) - Instance from util directory
   - AbstractRenderer class (type: AbstractRenderer) - Base renderer interface
   - DefaultRenderer class (type: DefaultRenderer) - Default content rendering
   - DataProvider abstract class (type: DataProvider) - Base DataProvider interface
   - TemplateParser class (type: TemplateParser) - Template parsing functionality
   - HtmlBakery main class (type: HtmlBakery) - Main framework orchestrator

2. **Template Structure:**
   - JSON template root can be array or tag node
   - Tag node structure:
     {
       "tag": "div",
       "attrs": ["class": "container"],
       "dataProvider": "MyDataProvider",
       "renderer": "MyCustomRenderer",
       "content": "Hello World"
     }

3. **Template Processing:**
   - Parse JSON templates
   - Handle tag nodes with content
   - Render content using default or custom renderers
   - Process DataProvider objects when specified

4. **Testing:**
   - Generate test cases covering all template variants
   - Create HTML test report
   - Verify framework functionality
   - Include complex example with custom DataProvider and Renderer:
     - Base tag is a static div
     - Contains a table tag with thead and tbody
     - tbody uses a DataProvider to populate rows
     - tbody content is a tr tag node with tablefields renderer that renders td elements

5. **Regeneration:**
   - The framework can be regenerated with all components implemented
   - All core classes are properly structured and fully functional
   - Testing framework includes both basic and complex examples
   - All requirements from this document will be satisfied by regeneration

## Implementation Plan

1. **Phase 1: Core Framework Components**
   - Implement HtmlBuilder class
   - Create AbstractRenderer and DefaultRenderer
   - Develop DataProvider base classes
   - Design TemplateParser

2. **Phase 2: Template Processing**
   - Implement JSON template parsing
   - Add custom renderer support
   - Implement DataProvider functionality

3. **Phase 3: Test Generation**
   - Generate test classes and templates
   - Create test reporting system

4. **Phase 4: Documentation & Integration**
   - Finalize project documentation
   - Implement integration capabilities