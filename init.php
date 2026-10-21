<?php
/**
 * PLM Plugin Initialization
 * Ordered by dependency chain to ensure base classes are loaded before derived classes.
 */

// 1. Core Utilities (Used by Renderers and Macros)
require_once(__DIR__ . '/src/util/SubstitutionUtility.php');
require_once(__DIR__ . '/src/util/HtmlContext.php');
require_once(__DIR__ . '/src/util/Writer.php');
require_once(__DIR__ . '/src/util/RenderWriter.php');
require_once(__DIR__ . '/src/util/ResponseWriter.php');
require_once(__DIR__ . '/src/util/HtmlBuilder.php');

// 2. Persistence & Core Database Objects (Base for almost everything)
require_once(__DIR__ . '/src/persist/DbObject.php');
require_once(__DIR__ . '/src/persist/DbEnum.php');
require_once(__DIR__ . '/src/persist/PlmDB.php');
require_once(__DIR__ . '/src/persist/Category.php');
require_once(__DIR__ . '/src/persist/Status.php');
require_once(__DIR__ . '/src/persist/Part.php');
require_once(__DIR__ . '/src/persist/PartItemRef.php');
require_once(__DIR__ . '/src/persist/PartVersion.php');
require_once(__DIR__ . '/src/persist/Product.php');
require_once(__DIR__ . '/src/persist/ProductVariant.php');
require_once(__DIR__ . '/src/persist/VariantItemRef.php');
require_once(__DIR__ . '/src/persist/Company.php');
require_once(__DIR__ . '/src/persist/CompanyType.php');

// 3. Factories (Base for constructing components)
require_once(__DIR__ . '/src/service/HtmlBaseFactory.php');

require_once(__DIR__ . '/src/service/PlmMgrBase.php');
require_once(__DIR__ . '/src/service/PlmMgrParts.php');
require_once(__DIR__ . '/src/service/PlmMgrVersions.php');
require_once(__DIR__ . '/src/service/PlmMgrProducts.php');
require_once(__DIR__ . '/src/service/PlmMgrCompanies.php');
require_once(__DIR__ . '/src/service/PlmMgrCompanyTypes.php');
require_once(__DIR__ . '/src/service/PlmMgrCategories.php');
require_once(__DIR__ . '/src/service/PlmMgrVariants.php');
require_once(__DIR__ . '/src/service/PlmMgrStatus.php');
require_once(__DIR__ . '/src/service/PlmMgrService.php');

// 4. API Layer (Depends on Persistence)
require_once(__DIR__ . '/src/api/AbstractApi.php');
require_once(__DIR__ . '/src/api/ContentApi.php');
require_once(__DIR__ . '/src/api/PagesApi.php');

// 5. Macro System (Depends on almost everything above)
require_once(__DIR__ . '/src/macro/RenderContext.php');
require_once(__DIR__ . '/src/macro/PlmMacro.php');
require_once(__DIR__ . '/src/macro/PlmBuilderMacro.php');
require_once(__DIR__ . '/src/macro/PlmBomMacro.php');
require_once(__DIR__ . '/src/macro/PlmManager.php');


define('PLM_HTML_DIR', __DIR__ . '/res/html');
