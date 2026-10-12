<?php

/**
 * Service for fetching data related to the PLM Manager views.
 */
class PlmManagerService {

    /**
     * Get data for the requested view.
     * 
     * @param string $view
     * @return array
     */
    public static function getData(string $view): array {
        $db = PlmDB::get();

        switch ($view) {
            case 'parts':
                $parts = Part::entries($db);
                $partsArray = [];
                foreach ($parts as $part) {
                    $partsArray[] = [
                        'id'           => $part->pk,
                        'ipn'          => $part->ipn,
                        'description'   => $part->description,
                        'category_id'  => $part->categoryId,
                        'category_name' => $part->categoryId ? Category::fetchName($db, $part->categoryId) : 'N/A'
                    ];
                }
                return [
                    'parts' => $partsArray,
                    'html' => ViewRenderer::renderParts($partsArray)
                ];
            case 'versions':
                $versions = PartVersion::entries($db);
                $versionsArray = [];
                foreach ($versions as $v) {
                    $versionsArray[] = [
                        'id'           => $v->pk,
                        'version_id'   => $v->name,
                        'part_id'      => $v->partId,
                        'status_id'    => $v->statusId,
                        'major'         => $v->major,
                        'revision'      => $v->revision,
                        'status_name'  => $v->statusId ? Status::fetchName($db, $v->statusId) : 'N/A'
                    ];
                }
                return [
                    'versions' => $versionsArray,
                    'html' => ViewRenderer::renderVersions($versionsArray)
                ];
            case 'bom':
                $bom = PartItemRef::entries($db);
                $bomArray = [];
                foreach ($bom as $item) {
                    $bomArray[] = [
                        'id'               => $item->pk,
                        'part_id'          => $item->parentVersionId,
                        'component_id'     => $item->childVersionId,
                        'quantity'          => $item->quantity,
                        'parent_version_id'=> $item->parentVersionId,
                        'child_version_id' => $item->childVersionId,
                        'designators'       => $item->designators
                    ];
                }
                return [
                    'bom' => $bomArray,
                    'html' => ViewRenderer::renderBom($bomArray)
                ];
            case 'products':
                $products = Product::entries($db);
                $productsArray = [];
                foreach ($products as $p) {
                    $productsArray[] = [
                        'id'           => $p->pk,
                        'product_id'   => $p->name,
                        'description'   => $p->description
                    ];
                }
                return [
                    'products' => $productsArray,
                    'html' => ViewRenderer::renderProducts($productsArray)
                ];
            case 'companies':
                $companies = Company::entries($db);
                $companiesArray = [];
                foreach ($companies as $c) {
                    $companiesArray[] = [
                        'id'           => $c->id,
                        'name'         => $c->name,
                        'description'   => $c->description,
                        'type_id'      => $c->type_id,
                        'type_name'    => $c->type_id ? CompanyType::fetchName($db, $c->type_id) : 'N/A'
                    ];
                }
                return [
                    'companies' => $companiesArray,
                    'html' => ViewRenderer::renderCompanies($companiesArray)
                ];
            case 'master_data':
                $data = PlmMgrMasterDataService::getDataMasterData();
                return [
                    'master_data' => $data,
                    'html' => ViewRenderer::renderMasterData($data)
                ];
            default:
                return [];
        }
    }

    /**
     * Create a new Master Data entry.
     * 
     * @param string $type
     * @param array $data
     * @return object
     */
    public static function createMasterData(string $type, array $data) {
        return PlmMgrMasterDataService::create($type, $data);
    }

    /**
     * Update an existing Master Data entry.
     * 
     * @param string $type
     * @param int $pk
     * @param array $data
     * @return object
     */
    public static function updateMasterData(string $type, int $pk, array $data) {
        return PlmMgrMasterDataService::update($type, $pk, $data);
    }

    /**
     * Delete a Master Data entry.
     * 
     * @param string $type
     * @param int $pk
     * @return bool
     */
    public static function deleteMasterData(string $type, int $pk) {
        return PlmMgrMasterDataService::delete($type, $pk);
    }
}
