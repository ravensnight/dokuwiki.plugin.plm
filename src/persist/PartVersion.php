<?php

class PartVersion extends DbObject {

    public string $name;
    public int $partId;
    public int $statusId;

    public int $major = -1;
    public int $revision = -1;

    public function assign(array $fields) {
        $this->name = $fields['version_id'];
        $this->major = $fields['major'];
        $this->revision = $fields['revision'];
        $this->partId = $fields['part_id'];
        $this->statusId = $fields['status_id'];
    }

    public static function createNew(Part $part, Status $status, string $name, int $major = 1, int $revision = 1) : self {
        return new self([
            'version_id' => $name,
            'major' => $major,
            'revision' => $revision,
            'part_id' => $part->pk,
            'status_id' => $status->pk
        ]);
    }

    public static function byPK(PlmDB $db, int $pk): ?static
    {
        $result = $db->querySingle("SELECT * FROM  plm_part_version WHERE id = :pk;", [
            'pk' => $pk
        ]);

        if ($result) {
            return new PartVersion($result);
        }

        return null;
    }

    public static function byName(PlmDB $db, string $name): ?self
    {

        $result = $db->querySingle("SELECT * FROM  plm_part_version WHERE version_id = :version_id;", [
            'version_id' => $name
        ]);

        if ($result) {
            return new PartVersion($result);
        }

        return null;
    }

    /**
     * @return PartVersion[]
     */
    public static function entries(PlmDB $db): array
    {
        $rows = $db->fetchAll("SELECT * FROM plm_part_version ORDER BY id ASC;");
        $res = [];
        foreach ($rows as $row) {
            $res[] = new PartVersion($row);
        }
        return $res;
    }

    /**
     * @return PartVersion[]
     */
    public static function byPartId(PlmDB $db, int $partId): ?array {

        $result = $db->fetchAll("SELECT * FROM  plm_part_version WHERE part_id = :part_id;", [
            'part_id' => $partId
        ]);

        if ($result) {
            $res = [];
            foreach ($result as $row) {
                $res[] = new PartVersion($row);
            }
            return $res;
        }

        return null;
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
            $q = "INSERT INTO plm_part_version (version_id, part_id, status_id, major, revision) VALUES (:version_id, :part_id, :status_id, :major, :revision)";

            $db->querySingle($q, [
                'version_id' => $this->name,
                'part_id' => $this->partId,
                'status_id' => $this->statusId,
                'major' => $this->major,
                'revision' => $this->revision
            ]);

            // Get the newly created ID and assign it to this object
            $this->pk = $db->lastInsertedId('plm_part_version');
        } else {
            // Update existing part version
            $q = "UPDATE plm_part_version SET version_id = :version_id, part_id = :part_id, status_id = :status_id, major = :major, revision = :revision WHERE id = :id";

            $db->querySingle($q, [
                'id' => $this->pk,
                'version_id' => $this->name,
                'part_id' => $this->partId,
                'status_id' => $this->statusId,
                'major' => $this->major,
                'revision' => $this->revision
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
        $q = "DELETE FROM plm_part_version WHERE id = :id";
        $db->querySingle($q, ['id' => $this->pk]);
    }
}
