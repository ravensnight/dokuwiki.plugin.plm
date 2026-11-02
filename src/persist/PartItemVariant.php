<?php

class PartItemVariant extends DbObject {

    public int $partItemId;
    public int $variantId;

    public function assign(array $fields) {
        $this->partItemId = $fields['part_item_id'];
        $this->variantId = $fields['variant_id'];
    }

    public static function createNew(int $partItemId, int $variantId) : self {
        return new self([
            'part_item_id' => $partItemId,
            'variant_id' => $variantId
        ]);
    }

    public static function byPK(PlmDB $db, int $pk) : ?static {
        /** @var string */
        $q = "SELECT * FROM plm_part_item_variant WHERE id = :pk;";
        $result = $db->querySingle($q, [
            'pk' => $pk
        ]);

        if ($result) {
            return new self($result);
        }

        return null;
    }

    /**
     * @return PartItemVariant[]
     */
    public static function byPartItemId(PlmDB $db, int $partItemId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_part_item_variant WHERE part_item_id = :pk;";
        $result = $db->fetchAll($q, [
            'pk' => $partItemId
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

    /**
     * @return PartItemVariant[]
     */
    public static function byVariantId(PlmDB $db, int $variantId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_part_item_variant WHERE variant_id = :pk;";
        $result = $db->fetchAll($q, [
            'pk' => $variantId
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

    /**
     * @return PartItemVariant[]
     */
    public static function entries(PlmDB $db): array {
        $rows = $db->fetchAll("SELECT * FROM plm_part_item_variant ORDER BY id ASC;");
        $items = [];
        foreach ($rows as $row) {
            $items[] = new self($row);
        }
        return $items;
    }

    /**
     * Check if a variant is assigned to a part item
     */
    public static function hasVariant(PlmDB $db, int $partItemId, int $variantId) : bool {
        $q = "SELECT count(*) as amount FROM plm_part_item_variant WHERE part_item_id = :part_item_id AND variant_id = :variant_id";

        $result = $db->querySingle($q, [
            'part_item_id' => $partItemId,
            'variant_id' => $variantId
        ]);

        return $result["amount"] > 0;
    }

    /**
     * Assign a variant to a part item
     */
    public static function assignVariant(PlmDB $db, int $partItemId, int $variantId) {
        // Check if the pair already exists
        if (static::hasVariant($db, $partItemId, $variantId)) {
            // Do nothing - already assigned
            return;
        } else {
            // Insert a new assignment
            $q = "INSERT INTO plm_part_item_variant (part_item_id, variant_id) VALUES (:part_item_id, :variant_id)";
            $db->querySingle($q, [
                'part_item_id' => $partItemId,
                'variant_id' => $variantId
            ]);
        }
    }

    /**
     * Remove a variant assignment from a part item
     */
    public static function removeVariant(PlmDB $db, int $partItemId, int $variantId) {
        // Delete the assignment if it exists
        $q = "DELETE FROM plm_part_item_variant WHERE part_item_id = :part_item_id AND variant_id = :variant_id";
        $db->querySingle($q, [
            'part_item_id' => $partItemId,
            'variant_id' => $variantId
        ]);
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void {
        if (empty($this->pk)) {
            $q = "INSERT INTO plm_part_item_variant (part_item_id, variant_id) VALUES (:part_item_id, :variant_id)";
            $db->querySingle($q, [
                'part_item_id' => $this->partItemId,
                'variant_id' => $this->variantId
            ]);
            $this->pk = $db->lastInsertedId('plm_part_item_variant');
        } else {
            $q = "UPDATE plm_part_item_variant SET part_item_id = :part_item_id, variant_id = :variant_id WHERE id = :id";
            $db->querySingle($q, [
                'id' => $this->pk,
                'part_item_id' => $this->partItemId,
                'variant_id' => $this->variantId
            ]);
        }
    }

    /**
     * Delete the referenced object
     */
    public function delete(PlmDB $db, bool $approve): void {
        if (!$approve || empty($this->pk)) {
            return;
        }
        $q = "DELETE FROM plm_part_item_variant WHERE id = :id";
        $db->querySingle($q, ['id' => $this->pk]);
    }

    /**
     * Get the part item this variant assignment refers to
     */
    public function fetchPartItem(PlmDB $db): ?PartItemRef {
        // This would need to be implemented by getting the part_item_id
        // and then looking up the PartItemRef
        return PartItemRef::byPK($db, $this->partItemId);
    }

    /**
     * Get the variant this assignment refers to
     */
    public function fetchVariant(PlmDB $db): ?Variant {
        return Variant::byPK($db, $this->variantId);
    }
}
?>