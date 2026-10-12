<?php

class PlmMgrProducts extends PlmMgrBase {

    #[Override]
    public function getData(): mixed
    {
        $db = PlmDB::get();

        /** @var Product[] $products */
        $products = Product::entries($db);

        $result = [];
        foreach ($products as $prod) {

            $result[] = [
                "id" => $prod->pk,
                "name" => $prod->name,
                "description" => $prod->description,
            ];
        }

        return $result;
    }
}