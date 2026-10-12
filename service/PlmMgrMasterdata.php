<?php

class PlmMgrMasterdata extends PlmMgrBase {

    /** @param DbEnum[] $enumList */
    private function transform(array $enumList) : array {
        $result = [];

        foreach ($enumList as $entry) {

            $result[] = [
                "id" => $entry->pk,
                "name" => $entry->name,
                "description" => $entry->description,
                "sortorder" => $entry->sort_order,
                "status" => $entry->active ? 'active' : 'inactive'
            ];
        }

        return $result;
    }

    #[Override]
    public function getData(): mixed
    {
        $db = PlmDB::get();
        $result = [];

        /** @var Category[] */
        $categories = Category::fetchAll($db);
        $result[ "categories" ] = $this->transform($categories);
        
        /** @var CompanyType[] */
        $companyTypes = CompanyType::fetchAll($db);
        $result["companyTypes"] = $this->transform($companyTypes);

        /** @var Status[] */
        $status = Status::fetchAll($db);
        $result["status"] = $this->transform($status);

        return $result;
    }
}