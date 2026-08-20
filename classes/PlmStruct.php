<?php

class PlmStruct
{
    private const FILTER_OPERATORS = [
        '>=',
        '<=',
        '!=',
        '!~',
        '=*',
        '=',
        '<',
        '>',
        '~',
    ];

    /**
     * Parse a single Struct filter.
     */
    private function parseFilter(string $filter): array
    {
        $filter = trim($filter);

        foreach (self::FILTER_OPERATORS as $operator) {

            $pos =
                strpos(
                    $filter,
                    $operator
                );

            if ($pos === false) {
                continue;
            }

            $field =
                trim(
                    substr(
                        $filter,
                        0,
                        $pos
                    )
                );

            $value =
                trim(
                    substr(
                        $filter,
                        $pos + strlen($operator)
                    )
                );

            if ($field === '') {
                throw new \RuntimeException(
                    'Filter field must not be empty.'
                );
            }

            if ($value === '') {
                throw new \RuntimeException(
                    'Filter value must not be empty.'
                );
            }

            return [
                $field,
                $operator,
                $value,
                'OR',
            ];
        }

        throw new \RuntimeException(
            'Invalid filter syntax. Expected field/operator/value.'
        );
    }

    /**
     * Search Struct records.
     */
    public function search(
        string $schema,
        array $fields,
        ?string $filter = null
    ): array {

        if ($schema === '') {
            throw new \RuntimeException(
                'No Struct schema specified.'
            );
        }

        if (empty($fields)) {
            throw new \RuntimeException(
                'No Struct fields specified.'
            );
        }

        $fields =
            array_values(
                array_unique(
                    $fields
                )
            );

        if (
            $filter !== null &&
            trim($filter) !== ''
        ) {

            $parsed =
                $this->parseFilter(
                    $filter
                );

            $filterField =
                $parsed[0];

            if (
                !in_array(
                    $filterField,
                    $fields,
                    true
                )
            ) {
                $fields[] =
                    $filterField;
            }
        }

        $structConfig = [
            'schemas' => [
                [$schema, ''],
            ],
            'cols' => $fields,
        ];

        if (
            $filter !== null &&
            trim($filter) !== ''
        ) {
            $structConfig['filter'] = [
                $this->parseFilter(
                    $filter
                ),
            ];
        }

        $search =
            new \dokuwiki\plugin\struct\meta\SearchConfig(
                $structConfig
            );

        return [
            'search' => $search,
            'rows' => $search->getRows(),
        ];
    }

    /**
     * Find the first Struct record matching a filter.
     */
    public function findOne(
        string $schema,
        string $filter
    ): ?array {

        if (trim($filter) === '') {
            throw new \RuntimeException(
                'Cannot find a Struct record without a filter.'
            );
        }

        /*
         * Determine the field used by the filter.
         */
        $parsed =
            $this->parseFilter(
                $filter
            );

        $filterField =
            $parsed[0];

        /*
         * Use the same search mechanism as PlmSelect.
         */
        $result =
            $this->search(
                $schema,
                [$filterField],
                $filter
            );

        $search =
            $result['search'];

        $rows =
            $result['rows'];

        if (empty($rows)) {
            return null;
        }

        $pids =
            $search->getPids();

        $rids =
            $search->getRids();

        return [
            'row' =>
                $rows[0],

            'pid' =>
                $pids[0] ?? '',

            'rid' =>
                (int) (
                    $rids[0] ?? 0
                ),
        ];
    }

    /**
     * Open an existing Struct record.
     */
    public function getAccessForRecord(
        string $schema,
        string $pid,
        int $rid
    ) {

        $schemaObject =
            new \dokuwiki\plugin\struct\meta\Schema(
                $schema
            );

        if (!$schemaObject->getId()) {
            throw new \RuntimeException(
                'Struct schema does not exist: ' .
                $schema
            );
        }

        /*
         * Page data.
         */
        if (
            $pid !== '' &&
            $rid === 0
        ) {
            return
                \dokuwiki\plugin\struct\meta\AccessTable::getPageAccess(
                    $schema,
                    $pid
                );
        }

        /*
         * Serial data.
         */
        if (
            $pid !== '' &&
            $rid > 0
        ) {
            return
                \dokuwiki\plugin\struct\meta\AccessTable::getSerialAccess(
                    $schema,
                    $pid,
                    $rid
                );
        }

        /*
         * Global data.
         */
        return
            \dokuwiki\plugin\struct\meta\AccessTable::getGlobalAccess(
                $schema,
                $rid
            );
    }

    /**
     * Create a new global Struct access object.
     */
    public function newGlobalAccess(
        string $schema
    ) {

        return
            \dokuwiki\plugin\struct\meta\AccessTable::getGlobalAccess(
                $schema
            );
    }

    /**
     * Get Struct values.
     */
    public function getData(
        $access
    ): array {

        return
            $access->getData();
    }

    /**
     * Get raw Struct values.
     */
    public function getDataArray(
        $access
    ): array {

        return
            $access->getDataArray();
    }

    /**
     * Save Struct data.
     */
    public function save(
        $access,
        array $data
    ): void {

        $validator =
            $access->getValidator(
                $data
            );

        if (!$validator->validate()) {

            $errors =
                $validator->getErrors();

            if (!empty($errors)) {
                throw new \RuntimeException(
                    implode(
                        ' ',
                        $errors
                    )
                );
            }

            throw new \RuntimeException(
                'Struct validation failed.'
            );
        }

        if (!$validator->hasChanges()) {
            throw new \RuntimeException(
                'No changes to save.'
            );
        }

        if (!$validator->saveData()) {
            throw new \RuntimeException(
                'Could not save Struct data.'
            );
        }
    }

    /**
     * Delete Struct data.
     */
    public function delete(
        $access
    ): void {

        $access->clearData();
    }
}