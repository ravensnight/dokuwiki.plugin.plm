<?php

/**
 * Service for handling Master Data (Categories and Company Types) logic.
 */
class PlmMgrMasterDataService {

    /**
     * Create a new Master Data entity.
     *
     * @param string $type 'Category' or 'Company Type'
     * @param array $data
     * @return object
     */
    public static function create(string $type, array $data) {
        $db = PlmDB::get();
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $active = $data['active'] ?? true;
        $sort_order = $data['sort_order'] ?? 0;

        if ($type === 'Category') {
            $entity = Category::createNew($name, $description, $active, $sort_order);
        } elseif ($type === 'Company Type') {
            $entity = CompanyType::createNew($name, $description, $active, $sort_order);
        } else {
            throw new Exception("Unknown entity type: $type");
        }

        $entity->save($db);
        return $entity;
    }

    /**
     * Update an existing Master Data entity.
     *
     * @param string $type 'Category' or 'Company Type'
     * @param int $pk
     * @param array $data
     * @return object
     */
    public static function update(string $type, int $pk, array $data) {
        $db = PlmDB::get();
        if ($type === 'Category') {
            $entity = Category::byPK($db, $pk);
        } elseif ($type === 'Company Type') {
            $entity = CompanyType::byPK($db, $pk);
        } else {
            throw new Exception("Unknown entity type: $type");
        }

        if (!$entity) {
            throw new Exception("Entity not found.");
        }

        $entity->assign($data);
        $entity->save($db);
        return $entity;
    }

    /**
     * Delete a Master Data entity.
     *
     * @param string $type 'Category' or 'Company Type'
     * @param int $pk
     * @return bool
     */
    public static function delete(string $type, int $pk) {
        $db = PlmDB::get();
        if ($type === 'Category') {
            $entity = Category::byPK($db, $pk);
        } elseif ($type === 'Company Type') {
            $entity = CompanyType::byPK($db, $pk);
        } else {
            throw new Exception("Unknown entity type: $type");
        }

        if (!$entity) {
            throw new Exception("Entity not found.");
        }

        $entity->delete($db, true);
        return true;
    }

    /**
     * Get all categories.
     *
     * @return array
     */
    public static function getDataCategories(): array {
        $db = PlmDB::get();

        // Fetch all categories
        $categories = Category::fetchAll($db);
        $categoriesArray = [];
        foreach ($categories as $category) {
            $categoriesArray[] = [
                'type' => 'Category',
                'id' => $category->pk,
                'name' => $category->name,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'active' => $category->active,
                'link' => 'edit-category-' . $category->pk
            ];
        }

        return $categoriesArray;
    }

    /**
     * Get all company types.
     *
     * @return array
     */
    public static function getDataCompanyTypes(): array {
        $db = PlmDB::get();

        // Fetch all company types
        $companyTypes = CompanyType::fetchAll($db);
        $companyTypesArray = [];
        foreach ($companyTypes as $companyType) {
            $companyTypesArray[] = [
                'type' => 'Company Type',
                'id' => $companyType->pk,
                'name' => $companyType->name,
                'description' => $companyType->description,
                'sort_order' => $companyType->sort_order,
                'active' => $companyType->active,
                'link' => 'edit-company-type-' . $companyType->pk
            ];
        }

        return $companyTypesArray;
    }

    /**
     * Get all master data (categories and company types) combined for backward compatibility.
     *
     * @return array
     */
    public static function getDataMasterData(): array {
        $categories = PlmMgrMasterDataService::getDataCategories();
        $companyTypes = PlmMgrMasterDataService::getDataCompanyTypes();

        // Combine both arrays
        $masterData = array_merge($categories, $companyTypes);

        return $masterData;
    }
}