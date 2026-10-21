<?php

class PlmMgrVariants extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            $prodId = PlmMgrBase::productId($params);

            if ($prodId === null) {
                return $this->getAll($db);
            }
            else {
                return $this->forProductId($db, $prodId);
            }
        }
        else {
            return $this->forPk($db, $key);
        }
    }

    protected function getAll(PlmDB $db) : array {
        /** @var ProductVariant[] $versions */
        $versions = ProductVariant::entries($db);

        $result = [];
        foreach ($versions as $v) {
            $result[] = $this->createVariantData($v);
        }

        return $result;
    }

    protected function forPk(PlmDB $db, int $key) : ?array {
        $v = ProductVariant::byPk($db, $key);
        if ($v === null) {
            return null;
        }
        return $this->createVariantData($v);
    }

    protected function forProductId(PlmDB $db, int $prodId) : ?array {
        $variants = ProductVariant::byProductId($db, $prodId);
        $result = [];

        if ($variants !== null) {
            foreach ($variants as $v) {
                $result[] = $this->createVariantData($v);
            }
        }

        return $result;
    }

    /**
     * Creates an array representation of a version
     *
     * @param ProductVariant $v
     * @return array
     */
    private function createVariantData($v): array
    {
        $db = PlmDB::get();

        return [
            "id" => $v->pk,
            "name" => $v->name,
            "description" => $v->description,
            "productId" => $v->productId
        ];
    }

    public function delete(array $inputData): void
    {
        $db = PlmDB::get();

        $id = isset($inputData['id']) ? $inputData['id'] : null;
        if ($id === null) {
            throw new Exception(("Version Id must not be empty."));
        }

        $v = ProductVariant::byPK($db, $id);
        if ($v === null) {
            throw new Exception(("Version not found for id: " . $id));
        }

        $v->delete($db, true);
    }

    #[Override]
    public function update(array $inputData): array
    {
        $db = PlmDB::get();

        $id = isset($inputData['id']) ? $inputData['id'] : null;
        if ($id === null) {
            throw new Exception("Variant Id must not be empty.");
        }

        $v = ProductVariant::byPK($db, $id);
        if ($v === null) {
            throw new Exception("Variant not found for id: " . $id);
        }

        // Update the version fields
        if (isset($inputData['productId'])) {
            $product = Product::byPK($db, $inputData['productId']);
            if ($product === null) {
                throw new Exception("Product ID not found: " . $inputData['productId']);
            }
            $v->productId = $product->pk;
        }

        $name = isset($inputData['name']) ? $inputData['name'] : null;        
        if ($name === null) {
            throw new Exception("Variant name must not be empty.");
        }        
        $v->name = $inputData['name'];

        if (isset($inputData['description'])) {
            $v->description = $inputData['description'];
        }

        // Save the updated version
        $v->save($db);

        // Return the updated version data
        return $this->createVariantData($v);
    }

    #[Override]
    public function create(array $inputData): array
    {
        $db = PlmDB::get();

        $product = Product::byPK($db, $inputData['productId']);
        if ($product === null) {
            throw new Exception("Product ID not found: " . $inputData['productId']);
        }

        $name = isset($inputData['name']) ? $inputData['name'] : null;
        if ($name === null) {
            throw new Exception("Variant name must not be empty.");
        }

        $desc = isset($inputData['description']) ? $inputData['description'] : '';

        $variant = ProductVariant::createNew($product, $name, $desc);
        $variant->save($db);

        return $this->createVariantData($variant);
    }

    
}