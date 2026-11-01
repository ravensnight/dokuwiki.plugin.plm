<?php

class Revision extends DbObject {

    public string $revisionTag;
    public ?string $reference;
    public int $partId;
    public int $statusId;

    public function assign(array $fields) {
        $this->revisionTag = $fields['revision_tag'];
        $this->reference = $fields['reference'];
        $this->partId = $fields['part_id'];
        $this->statusId = $fields['status_id'];
    }

    public static function createNew(Part $part, Status $status, string $revisionTag, ?string $reference = null) : self {
        return new self([
            'revision_tag' => $revisionTag,
            'reference' => $reference,
            'part_id' => $part->pk,
            'status_id' => $status->pk
        ]);
    }

    public static function byPK(PlmDB $db, int $pk): ?static
    {
        $result = $db->querySingle("SELECT * FROM  plm_part_revision WHERE id = :pk;", [
            'pk' => $pk
        ]);

        if ($result) {
            return new Revision($result);
        }

        return null;
    }

    public static function byRevisionTag(PlmDB $db, string $revisionTag): ?self
    {

        $result = $db->querySingle("SELECT * FROM  plm_part_revision WHERE revision_tag = :revision_tag;", [
            'revision_tag' => $revisionTag
        ]);

        if ($result) {
            return new Revision($result);
        }

        return null;
    }

    /**
     * @return Revision[]
     */
    public static function entries(PlmDB $db): array
    {
        $rows = $db->fetchAll("SELECT * FROM plm_part_revision ORDER BY revision_tag ASC;");
        $res = [];
        foreach ($rows as $row) {
            $res[] = new Revision($row);
        }
        return $res;
    }

    /**
     * @return Revision[]
     */
    public static function byPartId(PlmDB $db, int $partId): ?array {

        $result = $db->fetchAll("SELECT * FROM  plm_part_revision WHERE part_id = :part_id;", [
            'part_id' => $partId
        ]);

        if ($result) {
            $res = [];
            foreach ($result as $row) {
                $res[] = new Revision($row);
            }
            return $res;
        }

        return null;
    }

    /**
     * @return Revision[]
     */
    public static function fetchAllExcept(PlmDB $db, ?array $keys): ?array
    {

        if ($keys === null || empty($keys)) {
            return static::entries($db);
        }

        // Create parameter placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $query = "SELECT * FROM plm_part_revision WHERE id NOT IN ($placeholders) ORDER BY revision_tag ASC";

        $result = $db->fetchAll($query, $keys);

        if ($result) {
            $res = [];
            foreach ($result as $row) {
                $res[] = new Revision($row);
            }
            return $res;
        }

        return null;
    }

    public function isChildOf(PlmDB $db, int $parent) : bool {
        return PartItemRef::hasChild($db, $parent, $this->pk);
    }

    /**
     * @return PartItemRef[]
     */
    public function fetchChildren(PlmDB $db) : ?array {
        return PartItemRef::byParentVersionId($db, $this->pk);
    }

    /**
     * @return PartItemRef[]
     */
    public function fetchParents(PlmDB $db): ?array
    {
        return PartItemRef::byChildVersionId($db, $this->pk);
    }

    /**
     * The part this version belongs to
     */
    public function fetchPart(PlmDB $db) : Part {
        return Part::byPK($db, $this->partId);
    }

    /**
     * The part this version belongs to
     */
    public function fetchStatus(PlmDB $db): Status
    {
        return Status::byPK($db, $this->statusId);
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void
    {
        if (empty($this->pk)) {
            // Create new part version - get a new ID and insert
            $q = "INSERT INTO plm_part_revision (revision_tag, reference, part_id, status_id) VALUES (:revision_tag, :reference, :part_id, :status_id)";

            $db->querySingle($q, [
                'revision_tag' => $this->revisionTag,
                'reference' => $this->reference,
                'part_id' => $this->partId,
                'status_id' => $this->statusId
            ]);

            // Get the newly created ID and assign it to this object
            $this->pk = $db->lastInsertedId('plm_part_revision');
        } else {
            // Update existing part version
            $q = "UPDATE plm_part_revision SET revision_tag = :revision_tag, reference = :reference, part_id = :part_id, status_id = :status_id WHERE id = :id";

            $db->querySingle($q, [
                'id' => $this->pk,
                'revision_tag' => $this->revisionTag,
                'reference' => $this->reference,
                'part_id' => $this->partId,
                'status_id' => $this->statusId
            ]);
        }
    }

    /**
     * Delete the referenced object
     */
    public function delete(PlmDB $db, bool $approve): void
    {
        if (!$approve || empty($this->pk)) {
            return;
        }
        $q = "DELETE FROM plm_part_revision WHERE id = :id";
        $db->querySingle($q, ['id' => $this->pk]);
    }
}

?>