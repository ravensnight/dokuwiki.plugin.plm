<?php

class PlmMgrCompanies extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var Company[] */
            $companies = Company::entries($db);

            $result = [];
            foreach ($companies as $comp) {
                $result[] = $this->createCompanyData($db, $comp);
            }
        }
        else {
            $comp = Company::byPk($db, $key);
            return $this->createCompanyData($db, $comp);
        }

        return $result;
    }

    /**
     * Creates an array representation of a company
     *
     * @param Company $comp
     * @param ?CompanyType $type
     * @return array
     */
    private function createCompanyData(PlmDB $db, Company $comp): array
    {
        $type = $comp->fetchType($db);

        return [
            "id" => $comp->pk,
            "name" => $comp->name,
            "description" => $comp->description,
            "type" => $type ? $type->name : 'n/a'
        ];
    }
}