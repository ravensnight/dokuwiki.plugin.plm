<?php

class PlmMgrCategories extends PlmMgrBase {

    private function getCategoryData(Category $cat) : array {
        if ($cat === null) return [];

        return [
            "id" => $cat->pk,
            "name" => $cat->name,
            "description" => $cat->description,
            "sortorder" => $cat->sort_order,
            "active" => ($cat->active ? 1 : 0),
            "composite" => ($cat->isComposite ? 1 : 0)
        ];
    }

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var Category[] */
            $list = Category::fetchAll($db);
            
            $res = [];
            if ($list !== null) {
                foreach ($list as $cat) {
                    $res[] = $this->getCategoryData($cat);
                }
            }

            return $res;
        } else {

            /** @var Category */
            $cat = Category::byPK($db, $key);            
            return $this->getCategoryData($cat);
        }
    }

    public function create(array $inputData): array
    {
        if (!isset($inputData['name'])) {
            throw new Exception("Category name must not be empty");
        }

        $db = PlmDB::get();

        $name = $inputData['name'];
        $desc = $inputData['description'] ?? '';
        $active = $inputData['active'] ?? false;
        $sortOrder = $inputData['sortorder'] ?? 0;
        $composite = $inputData['composite'] ?? false;

        $cat = Category::createNew($name, $desc, $active, $sortOrder, $composite);
        $cat->save($db);

        return $this->getCategoryData($cat);
    }

    public function update(array $inputData, ?array $attrNames): array
    {
        if (!isset($inputData['id'])) {
            throw new Exception("Category ID not set for update.");            
        }

        $pk = $inputData['id'];
        $db = PlmDB::get();

        $cat = Category::byPK($db,$pk);
        if ($cat === null) {
            throw new Exception("Category not found for id " . $pk);
        }

        if ($inputData['name']) {
            $cat->name = $inputData['name'];
        }

        if (isset($inputData['description'])) {
            $cat->description = $inputData['description'];
        }

        if (isset($inputData['active'])) {
            $cat->active = ($inputData['active'] === 1);
        }

        if (isset($inputData['sortorder'])) {
            $cat->sort_order = $inputData['sortorder'];
        }

        if (isset($inputData['composite'])) {
            $cat->isComposite = ($inputData['composite'] === 1);
        }

        $cat->save($db);
        return $this->getCategoryData($cat);
    }

    public function delete(array $inputData): void
    {
        $pk = $inputData['id'];
        $db = PlmDB::get();

        $cat = Category::byPK($db, $pk);
        if ($cat === null) {
            throw new Exception("Category not found for id " . $pk);
        }

        $cat->delete($db, true);
    }

}