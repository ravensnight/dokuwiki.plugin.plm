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

// 2. Core Utilities (Used by Renderers and Macros)
require_once(__DIR__ . '/util/SubstitutionUtility.php');
require_once(__DIR__ . '/util/HtmlContext.php');
require_once(__DIR__ . '/util/Writer.php');
require_once(__DIR__ . '/util/RenderWriter.php');
require_once(__DIR__ . '/util/ResponseWriter.php');
require_once(__DIR__ . '/util/HtmlBuilder.php');

// 3. Factories (Base for constructing components)
require_once(__DIR__ . '/service/HtmlBaseFactory.php');
require_once(__DIR__ . '/service/PartFormFactory.php');
require_once(__DIR__ . '/service/PartListFactory.php');
require_once(__DIR__ . '/service/VersionFormFactory.php');

// 4. API Layer (Depends on Persistence)
require_once(__DIR__ . '/api/ApiBase.php');
require_once(__DIR__ . '/api/ApiPart.php');
require_once(__DIR__ . '/api/ApiVersion.php');
require_once(__DIR__ . '/api/Event.php');

// 5. UI Rendering System (Depends on Utilities & Factories)
require_once(__DIR__ . '/service/ui/provider/Provider.php');
require_once(__DIR__ . '/service/ui/provider/ProviderRegistry.php');
require_once(__DIR__ . '/service/ui/provider/SimpleProvider.php');
require_once(__DIR__ . '/service/ui/provider/CategoriesProvider.php');
require_once(__DIR__ . '/service/ui/provider/PartListProvider.php');
require_once(__DIR__ . '/service/ui/renderer/Renderer.php');
require_once(__DIR__ . '/service/ui/renderer/RendererRegistry.php');
require_once(__DIR__ . '/service/ui/renderer/DefaultRenderer.php');

require_once(__DIR__ . '/service/ui/HtmlBakery.php');

// 6. Macro System (Depends on almost everything above)
require_once(__DIR__ . '/macro/RenderContext.php');
require_once(__DIR__ . '/macro/PlmMacro.php');
require_once(__DIR__ . '/macro/PlmBuilderMacro.php');
require_once(__DIR__ . '/macro/PlmBakeryMacro.php');
require_once(__DIR__ . '/macro/PlmTest.php');
require_once(__DIR__ . '/macro/PlmBom.php');
require_once(__DIR__ . '/macro/PlmParts.php');
require_once(__DIR__ . '/macro/PlmPartEdit.php');
require_once(__DIR__ . '/macro/PlmVersionEdit.php');
require_once(__DIR__ . '/macro/PlmVersions.php');

define('PLM_PLUGIN_TEMPLATE_DIR', __DIR__ . '/service/ui/template');

