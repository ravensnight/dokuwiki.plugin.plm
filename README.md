# PLM DokuWiki Plugin

## Purpose
The PLM (Product Lifecycle Management) plugin provides a comprehensive Single Page Application (SPA) interface for managing product lifecycle data within DokuWiki. It enables users to efficiently manage parts, versions, bill of materials relationships, products, companies, and reference data through a unified administrative console.

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

## Key Components

### Data Models
The plugin implements a comprehensive data model with the following entities:
- **Core Entities**: Parts, Part Versions, Categories, Status, Companies, Products, Product Variants, BOM relationships
- **Database Schema**: Complex relational structure with proper foreign key constraints and cascening behaviors

### Service Layer
Provides core business logic and data orchestration:
- **PlmManagerService**: Main service layer for fetching data related to the PLM Manager views
- **Specialized Services**: PlmMgrParts, PlmMgrVersions, PlmMgrProducts, PlmMgrCompanies, PlmMgrMasterdata
- **Business Logic Separation**: Clear separation between API routing and business logic

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

## Architecture Notes
- **SPA Logic**: Managed via `js/manager.js` interacting with `api/` endpoints.
- **Schema Coverage**: Covers all entities defined in `db/update0001.sql`.
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
The plugin implements the following UI components through DokuWiki macros:

### Main Components
- **plm:manager**: A Single Page Application (SPA) for full CRUD management of Parts, Versions, and BOMs. It acts as the primary administrative console for the plugin.
- **plm:bom**: Bill of Materials rendering component

### Component Features
- Each macro component has specific functionality and parameters
- All components integrate with the service layer for data access
- Responsive UI that updates based on data changes

## Usage Examples

### Basic Usage
```
{{plm:manager}}
```

### Advanced Usage
```
{{plm:bom variant="my_variant_name"}}
```

### Configuration Parameters
```
{{plm:manager}}
{{plm:bom variant="my_variant_name"}}
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