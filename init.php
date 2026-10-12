<?php
/**
 * PLM Plugin Initialization
 * Ordered by dependency chain to ensure base classes are loaded before derived classes.
 */

// 1. Persistence & Core Database Objects (Base for almost everything)
require_once(__DIR__ . '/persist/DbObject.php');
require_once(__DIR__ . '/persist/DbEnum.php');
require_once(__DIR__ . '/persist/PlmDB.php');
require_once(__DIR__ . '/persist/Category.php');
require_once(__DIR__ . '/persist/Status.php');
require_once(__DIR__ . '/persist/Part.php');
require_once(__DIR__ . '/persist/PartItemRef.php');
require_once(__DIR__ . '/persist/PartVersion.php');
require_once(__DIR__ . '/persist/Product.php');
require_once(__DIR__ . '/persist/ProductVariant.php');
require_once(__DIR__ . '/persist/VariantItemRef.php');
require_once(__DIR__ . '/persist/Company.php');
require_once(__DIR__ . '/persist/CompanyType.php');

// 2. Core Utilities (Used by Renderers and Macros)
require_once(__DIR__ . '/util/SubstitutionUtility.php');
require_once(__DIR__ . '/util/HtmlContext.php');
require_once(__DIR__ . '/util/Writer.php');
require_once(__DIR__ . '/util/RenderWriter.php');
require_once(__DIR__ . '/util/ResponseWriter.php');
require_once(__DIR__ . '/util/HtmlBuilder.php');

// 3. Factories (Base for constructing components)
require_once(__DIR__ . '/service/HtmlBaseFactory.php');

// 4. API Layer (Depends on Persistence)
require_once(__DIR__ . '/api/AbstractApi.php');
require_once(__DIR__ . '/api/ContentApi.php');
require_once(__DIR__ . '/api/PagesApi.php');

require_once(__DIR__ . '/service/PlmMgrBase.php');
require_once(__DIR__ . '/service/PlmMgrParts.php');
require_once(__DIR__ . '/service/PlmMgrVersions.php');
require_once(__DIR__ . '/service/PlmMgrProducts.php');
require_once(__DIR__ . '/service/PlmMgrCompanies.php');
require_once(__DIR__ . '/service/PlmMgrMasterdata.php');
require_once(__DIR__ . '/service/PlmMgrService.php');

// 6. Macro System (Depends on almost everything above)
require_once(__DIR__ . '/macro/RenderContext.php');
require_once(__DIR__ . '/macro/PlmMacro.php');
require_once(__DIR__ . '/macro/PlmBuilderMacro.php');
require_once(__DIR__ . '/macro/PlmBomMacro.php');
require_once(__DIR__ . '/macro/PlmManager.php');

define('PLM_PLUGIN_TEMPLATE_DIR', __DIR__ . '/service/ui/template');
define('PLM_PAGES_DIR', __DIR__ . '/service/ui/pages');
