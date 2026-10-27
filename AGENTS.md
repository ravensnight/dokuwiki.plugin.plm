# Purpose
This plugin provides PLM (product lifecycle management) capabilities for DokuWiki

# Architecture

## Dependencies

- `Alpine.js` : JavaScript interaction framework
- `sqlite` : SQList DokuWiki plugin, which handles integration of SQLite database tables.

## File and Directory Structure

### PHP Files
- `./action.php` : Main entry for Wiki interactions and API requests
- `./syntax.php` : Main entry for Macro rendering
- `./init.php` : Loads all custom PHP files being registered here. action.php and syntax.php include init.php only. 
- `./src/api` : Rest API Service implementations
- `./src/macro` : PLM Macro implementations, which serve as PLM entry on DokuWiki pages.
- `./src/persist` : DB Connector and DAO class implementations
- `./src/service` : Service Implementations. Main entry class for API calls is PlmMgrService.
- `./src/util` : Helper classes

### JavaScript Files
- `./script.js` : Initializer script, which loads all required JavaScript file on a page using this plugin. Each new JavaScript file needs to be registered here.
- `./res/js` : External library files
- `./res/js/plm` : Project javascript files

### HTML Fragments
- `./res/html` : Html fragments/snippets being loaded via REST api into PLM manager page sections.

### DB Scripts
- `./db/update*.sql`: SQL Initializer/Update scripts, which care about the database structure being up to date.

## JavaScript Class Structure

### Core Classes
- `PageFragment` : Abstract interface defining the contract for page fragments that can render HTML content
- `ItemContainer` : Manages container for PLM items with CRUD operations

### Service Classes
- `AppControl` : Application control functionality
- `BOMControl` : BOM (Business Object Model) control functionality
- `PartDetails` : Version management functionality
- `Masterdata` : Master data management functionality
- `Part` : Part management functionality
- `Product` : Product management functionality
- `Company` : Company management functionality

### Utility Classes
- `Page` : Page loading and rendering utilities