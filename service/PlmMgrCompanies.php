<?php

class PlmMgrCompanies extends PlmMgrBase {

    #[Override]
    public function getData(): mixed
    {
        $db = PlmDB::get();

        /** @var Company[] */
        $companies = Company::entries($db);

        $result = [];
        foreach ($companies as $comp) {

            $type = $comp->fetchType($db);

            $result[] = [
                "id" => $comp->pk,
                "name" => $comp->name,
                "description" => $comp->description,
                "type" => $type ? $type->name : 'n/a'
            ];
        }

        return $result;
    }
}