<?php

class ProductVariant extends DbObject {

    public int $productId;
    public string $name;
    public ?string $description = null;
    public ?int $partVersionId = null;

    protected function assign(array $fields) {
        $this->name = $fields['variant_id'];
        $this->productId = $fields['product_id'];
        $this->description = $fields['description'];
        $this->partVersionId = $fields['part_version_id'];
    }

    /**
     * Create new instance
     */
    public static function createNew(Product $product, string $name, ?string $description, ?int $partVersionId = null) : self {
        return new self([
            'variant_id' => $name,
            'product_id' => $product->pk,
            'description' => $description,
            'part_version_id' => $partVersionId
        ]);
    }

    /**
     * @result self[]
     */
    public static function entries(PlmDB $db): array {

        $list = $db->fetchAll("SELECT * FROM plm_product_variant");
        $result = [];

        if ($list) {
            foreach ($list as $entry) {
                $result[] = new self($entry);
            }
        }

        return $result;
    }

    /**
     * Find a product variant by key.
     */
    public static function byPK(PlmDB $db, int $pk): ?static
    {
        $result = $db->querySingle("SELECT * FROM plm_product_variant WHERE id = :pk", [
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

        $result = $db->querySingle("SELECT * FROM plm_product_variant WHERE variant_id = :variant_id", [
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
     * Get the releated product
     */
    public function fetchPartVersion(PlmDB $db): ?Product
    {
        return PartVersion::byPK($db, $this->partVersionId);
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void
    {
        if (empty($this->pk)) {
            $q = "INSERT INTO plm_product_variant (variant_id, product_id, description, part_version_id) VALUES (:variant_id, :product_id, :description, :part_version_id)";
            $db->querySingle($q, [
                'variant_id' => $this->name,
                'product_id' => $this->productId,
                'description' => $this->description,
                'part_version_id' => $this->partVersionId
            ]);

            $this->pk = $db->lastInsertedId('plm_product_variant');

        } else {
            $q = "UPDATE plm_product_variant SET variant_id = :variant_id, product_id = :product_id, description = :description, part_version_id = :part_version_id WHERE id = :pk";
            $db->querySingle($q, [
                'pk' => $this->pk,
                'variant_id' => $this->name,
                'product_id' => $this->productId,
                'description' => $this->description,
                'part_version_id' => $this->partVersionId
            ]);
        }
    }

    /**
     * Delete the referenced object.
     */
    public function delete(PlmDB $db, bool $approve): void
    {
        if (!$approve || empty($this->pk)) {
            return;
        }
        $q = "DELETE FROM plm_product_variant WHERE id = :pk";
        $db->querySingle($q, ['pk' => $this->pk]);
    }
}
