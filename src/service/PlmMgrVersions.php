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
                throw new Exception(json_encode($params));
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
        /** @var PartVersion[] $versions */
        $versions = PartVersion::entries($db);

        $result = [];
        foreach ($versions as $v) {
            $result[] = $this->createVersionData($v);
        }

        return $result;
    }

    protected function forPk(PlmDB $db, int $key) : ?array {
        $v = PartVersion::byPk($db, $key);
        if ($v === null) {
            return null;
        }
        return $this->createVersionData($v);
    }

    protected function forPartId(PlmDB $db, int $partId) : ?array {
        $versions = PartVersion::byPartId($db, $partId);
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
     * @param PartVersion $v
     * @return array
     */
    private function createVersionData($v): array
    {
        $db = PlmDB::get();
        $p = $v->fetchPart($db);

        return [
            "id" => $v->pk,
            "partId" => $v->partId,
            "name" => $v->name,
            "major" => $v->major,
            "revision" => $v->revision,
            "statusId" => $v->statusId
        ];
    }

    public function delete(array $inputData): void
    {
        $db = PlmDB::get();

        $id = isset($inputData['id']) ? $inputData['id'] : null;
        if ($id === null) {
            throw new Exception(("Version Id must not be empty."));
        }

        $v = PartVersion::byPK($db, $id);
        if ($v === null) {
            throw new Exception(("Version not found for id: " . $id));
        }

        $v->delete($db, true);
    }

    #[Override]
    public function update(array $inputData): array
    {
        $db = PlmDB::get();

        $id = isset($inputData['id']) ? $inputData['id'] : null;
        if ($id === null) {
            throw new Exception("Version Id must not be empty.");
        }

        $v = PartVersion::byPK($db, $id);
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

        if (isset($inputData['name'])) {
            $v->name = $inputData['name'];
        }

        if (isset($inputData['major'])) {
            $v->major = $inputData['major'];
        }

        if (isset($inputData['revision'])) {
            $v->revision = $inputData['revision'];
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
            throw new Exception("Revision number is not set.");
        }

        if ($inputData['major'] === null) {
            throw new Exception("Major version number is not set.");
        }

        $name = $inputData['name'];
        if ($name === null) {
            $name = $part->ipn . '_v' . $inputData['major'] . '.rev' . $inputData['revision'];
        }

        $createdVersion = PartVersion::createNew($part, $stat, $name, $inputData['major'], $inputData['revision']);
        $createdVersion->save($db);

        return $this->createVersionData($createdVersion);
    }

    
}