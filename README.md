# PLM DokuWiki Plugin

## Architecture
This plugin consists of the following application layers:

### Integeration 
Responsible for the integration into dokuwiki itself.

- **Request/Response Handling:** done by `./lib/plugins/plm/action.php`
- **Page Processing/Rendering:** done by `./lib/plugins/plm/syntax.php`
- **Custom Javascript:** loaded from `./lib/plugins/plm/js`

### Persistance
Defines the storage base for PLM objects.
- **Framework:** uses dokuwiki SQLite plugin (`./lib/plugins/sqlite`) as base. 
- **Schema Files:** `./lib/plugins/plm/db`
- **PHP Sources:** `./lib/plugins/plm/persist`

### Utilities
Provides plugin helper classes.
- **PHP Sources:** `./lib/plugins/plm/utils`

### Services
Defines controller services that handle UI/API requests, fetch/write data to persistance and reply data to UI/API.

- **PHP Sources:** `./lib/plugins/plm/service`

### UI
Implements macros, being inserted to dokuwiki pages.
- **PHP Sources:** `./lib/plugins/plm/macro`

### API
Provides the PLM plugin REST API. This one is involved by *Request/Response Handling*
- **PHP Sources:** `./lib/plugins/plm/api`

## Common Rules

There is no use of PHP namespaces right now.

## Components

### HTML Template Framework
Builds a framework which shall be used in each UI Macro implementation in order to define a common behavior for data acquisition, rendering and communication.

- **PHP Sources:** `./lib/plugins/plm/service/ui`

#### Design Rules

##### HtmlBackery
The main component is a template parser ***HtmlBakery*** with a function `parse(...)`. This function shall have the following parameters:
- **writer:** an instance of `util/Writer.php` which is to be used for writing the html output.
- **receipt:** an xml template file name, to be parsed and rendered.
- **context:** some php array, which can be passed, to send some information for rendering.

Responsibilites:
- Validate and parse the xml receipt into an xml object
- Replace context references in all tag attributes. Context references are tag attribute values which use the pattern `{<name>}`. `<name>` has to be replaced by a corresponding value to be found in given **context** array or an empty string `''`, if the value does not exist.
- Call the renderers for top level tags taken from template/receipt.

Out of scope:
- HtmlBakery does NOT do implement any rendering logic on its own. Instead it always uses the help of at least the default renderer.

##### Renderer
Rendering, shall be done by implementations of the abstract class ***Renderer***. Renderers provide a render function, with:
- **writer:** the instance to write the output to
- **receipt:** a xml-tag object, which is already parsed
- **context:** some context which is passed to do some special rendering

Responsibilites:
- Read and extract all tag attributes, that begin with `plm:`. These tags control special behavior and must not be sent to output.
- Write the html start tag with all non-plm tag attributes for the current tag to output.
- Loop all contained sub tags and call the rendering for those.
- Write the closing html tag.
- A single default renderer class `DefaultRenderer` shall implement the standard behavior for all tags. If no different renderer is given in tag attribute `plm:renderer` this one shall be used.
- If an explicite renderer name is given in tag attribute `plm:renderer` the class shall be instanciated via reflection.
- Tag render functions in default renderer shall be visible and overwritable in derived clases

##### Provider
To be continued.


#### Template Rules
**Example:** given under plugin path `service/ui/template-example.xml`


