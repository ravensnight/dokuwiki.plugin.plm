<?php

class PlmStruct
{
    public const PRIMARY_KEY_FIELD = '_pk';

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

    private function parseFilter(
        string $filter
    ): array {

        $filter =
            trim(
                $filter
            );

        foreach (
            self::FILTER_OPERATORS
            as $operator
        ) {

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
                        $pos +
                        strlen($operator)
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
     *
     * _pk is never passed to Struct as a schema field.
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

        $fields =
            array_values(
                array_unique(
                    $fields
                )
            );

        $structFields =
            array_values(
                array_filter(
                    $fields,
                    function ($field) {
                        return
                            $field !==
                            self::PRIMARY_KEY_FIELD;
                    }
                )
            );

        $parsedFilter = null;

        if (
            $filter !== null &&
            trim($filter) !== ''
        ) {

            $parsedFilter =
                $this->parseFilter(
                    $filter
                );

            $filterField =
                $parsedFilter[0];

            if (
                $filterField ===
                self::PRIMARY_KEY_FIELD
            ) {

                /*
                 * _pk is handled by findOne().
                 *
                 * It must never reach SearchConfig.
                 */
                throw new \RuntimeException(
                    'PLM _pk filters must be resolved through findByPrimaryKey().'
                );
            }

            if (!in_array(
                $filterField,
                $structFields,
                true
            )) {

                $structFields[] =
                    $filterField;
            }
        }

        /*
         * Struct requires at least one real field.
         */
        if (empty($structFields)) {

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

            $schemaColumns =
                $schemaObject->getColumns();

            if (empty($schemaColumns)) {
                throw new \RuntimeException(
                    'Struct schema contains no fields: ' .
                    $schema
                );
            }

            $firstColumn =
                reset(
                    $schemaColumns
                );

            if ($firstColumn === false) {
                throw new \RuntimeException(
                    'Could not determine a Struct field.'
                );
            }

            $structFields[] =
                $firstColumn->getLabel();
        }

        $structConfig = [
            'schemas' => [
                [$schema, ''],
            ],
            'cols' =>
                $structFields,
        ];

        if ($parsedFilter !== null) {

            $structConfig['filter'] = [
                $parsedFilter,
            ];
        }

        $search =
            new \dokuwiki\plugin\struct\meta\SearchConfig(
                $structConfig
            );

        return [
            'search' =>
                $search,

            'rows' =>
                $search->getRows(),
        ];
    }

    /**
     * Find the first record matching a filter.
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

        $parsed =
            $this->parseFilter(
                $filter
            );

        if (
            $parsed[0] ===
            self::PRIMARY_KEY_FIELD
        ) {

            return $this->findByPrimaryKey(
                $schema,
                $parsed[2]
            );
        }

        $filterField =
            $parsed[0];

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

        $pid =
            $pids[0] ?? '';

        $rid =
            (int) (
                $rids[0] ?? 0
            );

        return [
            'row' =>
                $rows[0],

            'pid' =>
                $pid,

            'rid' =>
                $rid,

            '_pk' =>
                $rid,
        ];
    }

    /**
     * Find a record by its technical Struct RID.
     *
     * _pk is deliberately resolved outside SearchConfig.
     */
    public function findByPrimaryKey(
        string $schema,
        string $pk
    ): ?array {

        $pk =
            trim(
                $pk
            );

        if (
            $pk === '' ||
            !ctype_digit($pk)
        ) {
            throw new \RuntimeException(
                'PLM _pk must be a positive integer.'
            );
        }

        $wantedRid =
            (int) $pk;

        if ($wantedRid <= 0) {
            throw new \RuntimeException(
                'PLM _pk must be greater than zero.'
            );
        }

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

        $schemaColumns =
            $schemaObject->getColumns();

        if (empty($schemaColumns)) {
            return null;
        }

        $firstColumn =
            reset(
                $schemaColumns
            );

        if ($firstColumn === false) {
            return null;
        }

        $field =
            $firstColumn->getLabel();

        $result =
            $this->search(
                $schema,
                [$field]
            );

        $search =
            $result['search'];

        $rows =
            $result['rows'];

        $pids =
            $search->getPids();

        $rids =
            $search->getRids();

        foreach (
            $rows as $index => $row
        ) {

            $rid =
                (int) (
                    $rids[$index] ?? 0
                );

            if ($rid !== $wantedRid) {
                continue;
            }

            return [
                'row' =>
                    $row,

                'pid' =>
                    $pids[$index] ?? '',

                'rid' =>
                    $rid,

                '_pk' =>
                    $rid,
            ];
        }

        return null;
    }

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

        return
            \dokuwiki\plugin\struct\meta\AccessTable::getGlobalAccess(
                $schema,
                $rid
            );
    }

    public function newGlobalAccess(
        string $schema
    ) {

        return
            \dokuwiki\plugin\struct\meta\AccessTable::getGlobalAccess(
                $schema
            );
    }

    public function getData(
        $access
    ): array {

        return
            $access->getData();
    }

    public function getDataArray(
        $access
    ): array {

        return
            $access->getDataArray();
    }

    public function save(
        $access,
        array $data
    ): void {

        unset(
            $data[self::PRIMARY_KEY_FIELD]
        );

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

    public function delete(
        $access
    ): void {

        $access->clearData();
    }
}