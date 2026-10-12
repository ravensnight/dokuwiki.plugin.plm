<?php

class PartItemRef extends DbObject {

    public int $parentVersionId;
    public int $childVersionId;
    public int $quantity;
    public ?string $designators = null;

    public function assign(array $fields) {
        $this->parentVersionId = $fields['parent_version_id'];
        $this->childVersionId = $fields['child_version_id'];
        $this->quantity = $fields['quantity'];
        $this->designators = $fields['designators'] ?? null;
    }

    public function createNew(int $parentVersionId, int $childVersionId, int $quantity = 1, ?string $designators = null) {
        return new self([
            'parent_version_id' => $parentVersionId,
            'child_version_id' => $childVersionId,
            'quantity' => $quantity,
            'designators' => $designators
        ]);
    }

    public static function byPK(PlmDB $db, int $pk) : ?static {
        /** @var string */
        $q = "SELECT * FROM plm_part_item WHERE id = :pk;";
        $result = $db->querySingle($q, [
            'pk' => $pk
        ]);

        if ($result) {
            return new self($result);
        }

        return null;
    }

    /**
     * @return PartItemRef[]
     */
    public static function byParentVersionId(PlmDB $db, int $parentVersionId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_part_item WHERE parent_version_id = :pk;";
        $result = $db->fetchAll($q, [
            'pk' => $parentVersionId
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
     * @return PartItemRef[]
     */
    public static function byChildVersionId(PlmDB $db, int $childVersionId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_part_item WHERE child_version_id = :pk;";
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

    /**
     * @return PartItemRef[]
     */
    public static function entries(PlmDB $db): array {
        $rows = $db->fetchAll("SELECT * FROM plm_part_item ORDER BY id ASC;");
        $items = [];
        foreach ($rows as $row) {
            $items[] = new self($row);
        }
        return $items;
    }

    /**
     * @var ProductVariant[]
     */
    public function fetchVariants(PlmDB $db) : ?array {

        $q = 'SELECT pv.* FROM plm_product_variant pv JOIN plm_part_item_variant piv ON piv.variant_id = pv.id WHERE piv.part_item_id = :pk;';

        $result = $db->fetchAll($q, [
            'pk' => $this->pk
        ]);

        if ($result) {
            $res = [];
            foreach($result as $row) {
                $res[] = new ProductVariant($row);
            }

            return $res;
        }

        return null;
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void {
        if (empty($this->pk)) {
            $q = "INSERT INTO plm_part_item (parent_version_id, child_version_id, quantity, designators) VALUES (:parent_version_id, :child_version_id, :quantity, :designators)";
            $db->querySingle($q, [
                'parent_version_id' => $this->parentVersionId,
                'child_version_id' => $this->childVersionId,
                'quantity' => $this->quantity,
                'designators' => $this->designators
            ]);
            $this->pk = $db->lastInsertedId('plm_part_item');
        } else {
            $q = "UPDATE plm_part_item SET parent_version_id = :parent_version_id, child_version_id = :child_version_id, quantity = :quantity, designators = :designators WHERE id = :id";
            $db->querySingle($q, [
                'id' => $this->pk,
                'parent_version_id' => $this->parentVersionId,
                'child_version_id' => $this->childVersionId,
                'quantity' => $this->quantity,
                'designators' => $this->designators // Fixed typo: was $this->designators
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
        $q = "DELETE FROM plm_part_item WHERE id = :id";
        $db->querySingle($q, ['id' => $this->pk]);
    }
}