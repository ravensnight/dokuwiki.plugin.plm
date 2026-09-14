<?php

class ProductVariant extends DbObject {
    
    public int $productId;
    public string $name;
    public ?string $description = null;

    protected function assign(array $fields) {
        $this->name = $fields['variant_id'];
        $this->productId = $fields['product_id'];
        $this->description = $fields['description'];
    }

    /**
     * Create new instance
     */
    public static function createNew(string $name, Product $product, ?string $description) : self {
        return new self([
            'variant_id' => $name,
            'product_id' => $product->pk,
            'description' => $description
        ]);
    }

    /**
     * Find a product variant by key.
     */
    public static function byPK(PlmDB $db, int $pk): ?static
    {
        $result = $db->fetchSingle("SELECT * FROM plm_product_variant WHERE id = :pk", [
            'pk' => $pk
        ]);

        if ($result) {
            return new ProductVariant($result);
        }

        return null;
    }

    /**
     * Find a product variant by name.
     */
    public static function byName(PlmDB $db, string $name) : ?ProductVariant {

        $result = $db->fetchSingle("SELECT * FROM plm_product_variant WHERE variant_id = :variant_id", [
            'variant_id' => $name
        ]);

        if ($result) {
            return new ProductVariant($result);
        }

        return null;
    }

    /**
     * Find a product variant by product.
     * @return ProductVariant[]
     */
    public static function byProductId(PlmDB $db, int $product_id): ?array
    {
        $result = $db->fetchAll("SELECT * FROM plm_product_variant WHERE product_id = :product_id", [
            'product_id' => $product_id
        ]);

        if ($result) {
            $res = [];

            foreach ($result as $row) {
                $res[] = new ProductVariant($row);
            }

            return $res;
        }

        return null;
    }

    /**
     * Get the releated product
     */
    public function fetchProduct(PlmDB $db): ?Product
    {
        return Product::byPK($db, $this->productId);
    }

    /**
     * @return VariantItemRef[]
     */
    public function fetchChildren(PlmDB $db): ?array
    {
        return VariantItemRef::byProductVariantId($db, $this->pk);
    }
}
