<?php

abstract class PlmMgrBase {

    protected static function id(array $params) : ?int {
        return $params['id'];
    }

    protected static function partId(array $params): ?int
    {
        return $params['partId'];
    }

    /** Provide the data for a certain section */
    public abstract function getData(array $params) : ?array;

    public function create(array $inputData) : array {
        throw new Exception('Function "create" not implemented.');
    }

    public function update(array $inputData): array {
        throw new Exception('Function "update" not implemented.');
    }

    public function delete(array $inputData): void
    {
        throw new Exception('Function "delete" not implemented.');
    }

    protected static function transformEnum(DbEnum $entry) : array {
        return [
            "id" => $entry->pk,
            "name" => $entry->name,
            "description" => $entry->description,
            "sortorder" => $entry->sort_order,
            "status" => $entry->active ? 'active' : 'inactive'
        ];
    }

    /** @param DbEnum[] $enumList */
    protected static function transformEnumList(array $enumList): array
    {
        $result = [];

        foreach ($enumList as $entry) {
            $result[] = PlmMgrBase::transformEnum($entry);
        }

        return $result;
    }
}