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
        return [
            "id" => $comp->pk,
            "name" => $comp->name,
            "description" => $comp->description,
            "typeId" => $comp->type_id
        ];
    }

    /**
     * Create a new company
     *
     * @param array $data
     * @return array
     */
    #[Override]
    public function create(array $data): array
    {
        $db = PlmDB::get();

        // Validate required fields
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception('Name is required');
        }

        // Create company using createNew static method        
        $type = CompanyType::byPK($db, $data['typeId']);
        if ($type === null) {
            throw new Exception('Company type not found for id: ' . $data['typeId'] );
        }

        $company = Company::createNew($type, $data['name'], $data['description'] ?? '');

        // Save to database
        $company->save($db);

        // Return formatted company data
        return $this->createCompanyData($db, $company);
    }

    /**
     * Update an existing company
     *
     * @param array $data
     * @return array
     */
    #[Override]
    public function update(array $data, ?array $attrNames): array
    {
        $db = PlmDB::get();

        // Find the company
        $company = Company::byPk($db, $data['id']);
        if ($company === null) {
            throw new Exception('Company not found for id: ' . $data['id']);
        }

        // Validate required fields
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception('Name is required');
        }

        // Update the company
        $company->name = $data['name'];
        $company->description = $data['description'] ?? '';
        $company->type_id = $data['typeId'] ?? null;

        // Save to database
        $company->save($db);

        // Return formatted company data
        return $this->createCompanyData($db, $company);
    }

    /**
     * Delete an existing company
     *
     * @param array $data
     * @return void
     */
    #[Override]
    public function delete(array $data): void
    {
        $db = PlmDB::get();

        // Find the company
        $company = Company::byPk($db, $data['id']);
        if ($company === null) {
            throw new Exception('Company not found for id: ' . $data['id']);
        }

        // Delete from database
        $company->delete($db, true);
    }
}