<?php

class PlmMgrProducts extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var Product[] $products */
            $products = Product::entries($db);

            $result = [];
            foreach ($products as $prod) {
                $result[] = $this->createProductData($prod);
            }

            return $result;
        }
        else {
            $prod = Product::byPk($db, $key);
            if ($prod === null) {
                return null;
            }
            return $this->createProductData($prod);
        }
    }

    /**
     * Creates an array representation of a product
     *
     * @param Product $prod
     * @return array
     */
    private function createProductData($prod): array
    {
        return [
            "id" => $prod->pk,
            "name" => $prod->name,
            "description" => $prod->description,
        ];
    }

    /**
     * Create a new product
     *
     * @param array $data
     * @return array
     */
    #[Override]
    public function create(array $data): array
    {
        $db = PlmDB::get();

        // Validate required fields
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception('Name is required');
        }

        $product = Product::createNew($data['name'], $data['description'] ?? '');

        // Save to database
        $product->save($db);

        // Return formatted company data
        return $this->createProductData($product);
    }

    /**
     * Update an existing product
     *
     * @param array $data
     * @return array
     */
    #[Override]
    public function update(array $data): array
    {
        $db = PlmDB::get();

        // Find the product
        $product = Product::byPk($db, $data['id']);
        if ($product === null) {
            throw new Exception('Product not found for id: ' . $data['id']);
        }

        // Validate required fields
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception('Name is required');
        }

        // Update the company
        $product->name = $data['name'];
        $product->description = $data['description'] ?? '';

        // Save to database
        $product->save($db);

        // Return formatted company data
        return $this->createProductData($product);
    }

    /**
     * Delete an existing product
     *
     * @param array $data
     * @return void
     */
    #[Override]
    public function delete(array $data): void
    {
        $db = PlmDB::get();

        // Find the company
        $product = Product::byPk($db, $data['id']);
        if ($product === null) {
            throw new Exception('Company not found for id: ' . $data['id']);
        }

        // Delete from database
        $product->delete($db, true);
    }    
}