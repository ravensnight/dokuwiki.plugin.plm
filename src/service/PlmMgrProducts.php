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
}