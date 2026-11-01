<?php

class PlmMgrVersions extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            $partId = PlmMgrBase::partId($params);

            if ($partId === null) {
                return $this->getAll($db);
            }
            else {
                return $this->forPartId($db, $partId);
            }
        }
        else {
            return $this->forPk($db, $key);
        }
    }

    protected function getAll(PlmDB $db) : array {
        /** @var Revision[] $versions */
        $versions = Revision::entries($db);

        $result = [];
        foreach ($versions as $v) {
            $result[] = $this->createVersionData($v);
        }

        return $result;
    }

    protected function forPk(PlmDB $db, int $key) : ?array {
        $v = Revision::byPk($db, $key);
        if ($v === null) {
            return null;
        }
        return $this->createVersionData($v);
    }

    protected function forPartId(PlmDB $db, int $partId) : ?array {
        $versions = Revision::byPartId($db, $partId);
        $result = [];

        if ($versions !== null) {
            foreach ($versions as $v) {
                $result[] = $this->createVersionData($v);
            }
        }

        return $result;
    }

    /**
     * Creates an array representation of a version
     *
     * @param Revision $v
     * @return array
     */
    private function createVersionData($v): array
    {
        $db = PlmDB::get();
        $p = $v->fetchPart($db);
        $c = $p->fetchCategory($db);

        return [
            "id" => $v->pk,
            "revisionTag" => $v->revisionTag,
            "reference" => $v->reference,
            "statusId" => $v->statusId,
            "partId" => $v->partId,
            "partIPN" => $p->ipn,
            "partDescription" => $p->description,
            "partCategoryId" => $c->pk
        ];
    }

    public function delete(array $inputData): void
    {
        $db = PlmDB::get();

        $id = isset($inputData['id']) ? $inputData['id'] : null;
        if ($id === null) {
            throw new Exception(("Version Id must not be empty."));
        }

        $v = Revision::byPK($db, $id);
        if ($v === null) {
            throw new Exception(("Version not found for id: " . $id));
        }

        $v->delete($db, true);
    }

    #[Override]
    public function update(array $inputData, ?array $attrNames): array
    {
        $db = PlmDB::get();

        $id = isset($inputData['id']) ? $inputData['id'] : null;
        if ($id === null) {
            throw new Exception("Version Id must not be empty.");
        }

        $v = Revision::byPK($db, $id);
        if ($v === null) {
            throw new Exception("Version not found for id: " . $id);
        }

        // Update the version fields
        if (isset($inputData['partId'])) {
            $part = Part::byPK($db, $inputData['partId']);
            if ($part === null) {
                throw new Exception("Part ID not found: " . $inputData['partId']);
            }
            $v->partId = $part->pk;
        }

        if (isset($inputData['statusId'])) {
            $stat = Status::byPK($db, $inputData['statusId']);
            if ($stat === null) {
                throw new Exception("Status ID not found: " . $inputData['statusId']);
            }
            $v->statusId = $stat->pk;
        }

        if (isset($inputData['revisionTag'])) {
            $v->revisionTag = $inputData['revisionTag'];
        }

        if (isset($inputData['reference'])) {
            $v->reference = $inputData['reference'];
        }

        // Save the updated version
        $v->save($db);

        // Return the updated version data
        return $this->createVersionData($v);
    }

    #[Override]
    public function create(array $inputData): array
    {
        $db = PlmDB::get();

        $part = Part::byPK($db, $inputData['partId']);
        if ($part === null) {
            throw new Exception("Part ID not found: " . $inputData['partId']);
        }

        $stat = Status::byPK($db, $inputData['statusId']);
        if ($stat === null) {
            throw new Exception("Status ID not found: " . $inputData['statusId']);
        }

        if ($inputData['revision'] === null) {
            throw new Exception("Revision tag is not set.");
        }
        $revisionTag = $inputData['revision'];

        $createdVersion = Revision::createNew($part, $stat, $revisionTag, $inputData['reference']);
        $createdVersion->save($db);

        return $this->createVersionData($createdVersion);
    }

    
}