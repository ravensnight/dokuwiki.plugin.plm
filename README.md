# PLM DokuWiki Plugin

## Architecture
This plugin consists of the following application layers:

### Integration 
Responsible for the integration into dokuwiki itself.

- **Request/Response Handling:** done by `./lib/plugins/plm/action.php`
- **Page Processing/Rendering:** done by `./lib/plugins/plm/syntax.php`
- **Custom Javascript:** loaded from `./lib/plugins/plm/js`

### Persistence
Defines the storage base for PLM objects.
- **Framework:** uses dokuwiki SQLite plugin (`./lib/plugins/sqlite`) as base. 
- **Schema Files:** `./lib/plugins/plm/db`
- **PHP Sources:** `./lib/plugins/plm/persist`

### Utilities
Provides plugin helper classes.
- **PHP Sources:** `./lib/plugins/plm/util`

### Services
Defines controller services that handle UI/API requests, fetch/write data to persistence and reply data to UI/API.

- **PHP Sources:** `./lib/plugins/plm/service`

### UI
Implements macros, being inserted to dokuwiki pages.
- **PHP Sources:** `./lib/plugins/plm/macro`

### API
Provides the PLM plugin REST API. This one is involved by *Request/Response Handling*
- **PHP Sources:** `./lib/plugins/plm/api`

## Common Rules

- **Business Logic Separation:** API entry classes (e.g., `ApiManager.php`) must strictly handle request/response routing and validation. All business logic, data orchestration, and complex calculations must be implemented in the **Service** layer (e.g., `PlmManagerService.php`). Services may further delegate logic to specialized sub-services (e.g., `PlmMgrMasterDataService.php`).

There is no use of PHP namespaces right now.

## Components

### Data Models
The plugin implements a comprehensive data model with the following entities:
- **Core Entities**: Parts, Part Versions, Categories, Status, Companies, Products, Product Variants, BOM relationships
- **Database Schema**: Complex relational structure with proper foreign key constraints and cascening behaviors
- **Persistence Layer**: Extends `DbObject` base class with CRUD operations for each entity

### Service Layer
Provides core business logic and data orchestration:
- **PlmManagerService**: Main service layer for fetching data related to the PLM Manager views
- **PlmMgrMasterDataService**: Specialized service for master data management
- **Business Logic Separation**: Clear separation between API routing and business logic

### HTML Template Framework
Builds a framework which shall be used in each UI Macro implementation in order to define a common behavior for data acquisition, rendering and communication.

- **PHP Sources:** `./lib/plugins/plm/service/ui`

#### Design Rules

##### HtmlBakery
The main component is a template parser ***HtmlBakery*** with a function `parse(...)`. This function shall have the following parameters:
- **writer:** an instance of `util/Writer.php` which is to be used for writing the html output.
- **receipt:** an xml template file name, to be parsed and rendered.
- **context:** some php array, which can be passed, to send some information for rendering.

Responsibilities:
- Validate and parse the xml receipt into an xml object
- Replace context references in all tag attributes. Context references are tag attribute values which use the pattern `{<name>}`. `<name>` has to be replaced by a corresponding value to be found in given **context** array or an empty string `''`, if the value does not exist.
- Call the renderers for top level tags taken from template/receipt.

Out of scope:
- HtmlBakery does NOT implement any rendering logic on its own. Instead it always uses the help of at least the default renderer.

##### Renderer
Rendering, shall be done by implementations of the abstract class ***Renderer***. Renderers provide a render function, with:
- **writer:** the instance to write the output to
- **receipt:** a xml-tag object, which is already parsed
- **context:** some context which is passed to do some special rendering

Responsibilities:
- Read and extract all tag attributes, that begin with `plm:`. These tags control special behavior and must not be sent to output.
- Write the html start tag with all non-plm tag attributes for the current tag to output.
- Loop all contained sub tags and call the rendering for those.
- Write the closing html tag.
- A single default renderer class `DefaultRenderer` shall implement the standard behavior for all tags. If no different renderer is given in tag attribute `plm:renderer` this one shall be used.
- If an explicit renderer name is given in tag attribute `plm:renderer` the class shall be instantiated via reflection.
- Tag render functions in default renderer shall be visible and overwritable in derived classes

##### Provider
The provider system handles data acquisition and rendering for UI components:
- **Provider.php**: Base provider class
- **ProviderRegistry.php**: Registry for managing different provider types
- **SimpleProvider.php**: Basic provider implementation
- **CategoriesProvider.php**: Category-specific provider
- **PartListProvider.php**: Part list provider implementation

#### Template Rules
**Example:** given under plugin path `service/ui/template-example.xml`

## Database Schema
The plugin implements a complex relational database schema designed for product lifecycle management:

### Core Entities
- **Categories**: Product category definitions with sort order and active status
- **Status**: Part version status definitions (e.g., draft, released, deprecated)
- **Companies**: Organization definitions with company types
- **Parts**: Product component definitions with IPN (Internal Part Number)
- **Part Versions**: Version tracking for parts with major/revision numbers
- **Products**: Product definitions with product IDs
- **Product Variants**: Product variant definitions linked to products
- **BOM Relationships**: Bill of Materials relationships between parts and their components

### Relationships
- Parts have many versions (one-to-many)
- Versions can be linked to products and variants
- BOM relationships define hierarchical component structures
- All relationships have proper foreign key constraints with cascading behavior

### Schema Features
- Proper indexing for common query patterns
- Constraint checking for data integrity
- Cascade and restrict behaviors for referential integrity

## Architecture Notes
- **SPA Logic**: Managed via `js/manager.js` interacting with `api/` endpoints.
- **Schema Coverage**: Covers all entities defined in `db/update0001.sql`.
- **Template Integration**: Uses XML templates with context substitution for consistent rendering
- **JavaScript Integration**: Custom JavaScript components interact with API endpoints to provide SPA functionality

## JavaScript Integration
The plugin includes JavaScript components that provide SPA functionality:

### Manager JavaScript
- **File**: `js/manager.js`
- **Functionality**: Provides SPA logic for the manager interface
- **Communication**: Interacts with API endpoints for data management
- **Features**: CRUD operations, real-time updates, UI state management

### Integration Pattern
- JavaScript components are loaded via DokuWiki's JavaScript system
- Communicate with API endpoints using AJAX requests
- Handle user interactions and update UI based on API responses
- Provide responsive, interactive user experience

## Macro Components
The plugin implements various UI components through DokuWiki macros:

### Main Components
- **plm:manager**: A Single Page Application (SPA) for full CRUD management of Parts, Versions, and BOMs. It acts as the primary administrative console for the plugin.
- **plm:bom**: Bill of Materials rendering component
- **plm:parts**: Parts listing component
- **plm:partedit**: Part editing component
- **plm:versions**: Version listing component
- **plm:versionedit**: Version editing component
- **plm:test**: Testing component

### Component Features
- Each macro component has specific functionality and parameters
- All components integrate with the service layer for data access
- Consistent rendering using the template system
- Responsive UI that updates based on data changes

## Usage Examples

### Basic Usage
```
{{plm:manager}}
```

### Advanced Usage
```
{{plm:parts}}
{{plm:versions}}
{{plm:bom}}
```

### Configuration Parameters
```
{{plm:manager view="parts"}}
{{plm:manager view="versions"}}
{{plm:manager view="bom"}}
```

## Technical Specifications

### Service Layer Details
The service layer is designed with the following characteristics:
- **Stateless Design**: All services are stateless and designed for re-use across different UI components
- **Database Integration**: Services utilize the persistence layer for all database operations
- **Data Transformation**: Services handle data transformation and formatting for UI presentation
- **Error Handling**: Comprehensive error handling with meaningful error messages
- **Performance**: Optimized for efficient data retrieval and processing

### Database Schema Features
- **Foreign Key Constraints**: All relationships are properly enforced with foreign key constraints
- **Indexing Strategy**: Strategic indexing for common query patterns
- **Data Integrity**: Constraint checking for data validation
- **Referential Integrity**: Cascade and restrict behaviors for maintaining data consistency