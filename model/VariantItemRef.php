<?php

class VariantItemRef extends DbObject {
    
    public int $productVariantId;
    public int $childVersionId;
    public int $quantity;
    public ?string $designators = null;
    
    public function assign(array $fields) {
        $this->productVariantId = $fields['variant_id'];
        $this->childVersionId = $fields['version_id'];
        $this->quantity = $fields['quantity'];
    }

    public function createNew(int $productVariantId, int $childVersionId, int $quantity = 1) {
        return new self([
            'variant_id' => $productVariantId,
            'version_id' => $childVersionId,
            'quantity' => $quantity
        ]);
    }

    public static function byPK(PlmDB $db, int $pk) : ?static {
        /** @var string */
        $q = "SELECT * FROM  plm_product_variant_item WHERE id = :pk;";
        $result = $db->fetchSingle($q, [
            'pk' => $pk
        ]);

        if ($result) {
            return new self($result);
        }

        return null;
    }

    public static function byProductVariantId(PlmDB $db, int $productVariantId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM  plm_product_variant_item WHERE variant_id = :pk;";
        $result = $db->fetchAll($q, [
            'pk' => $productVariantId
        ]);

        if ($result) {
            $res = [];
            foreach ($result as $row ) {
                $res[] = new self($row);
            }

            return $res;
        }

        return null;
    }

    public static function byChildVersionId(PlmDB $db, int $childVersionId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM  plm_product_variant_item WHERE version_id = :pk;";
        $result = $db->fetchAll($q, [
            'pk' => $childVersionId
        ]);

        if ($result) {
            $res = [];
            foreach ($result as $row) {
                $res[] = new self($row);
            }

            return $res;
        }

        return null;
    }

    public function fetchChild(PlmDB $db) : ?PartVersion {
        return PartVersion::byPK($db, $this->childVersionId);
    }

    public function fetchParent(PlmDB $db): ?ProductVariant
    {
        return ProductVariant::byPK($db, $this->productVariantId);
    }
}
