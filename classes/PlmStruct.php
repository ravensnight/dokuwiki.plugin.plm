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

    /**
     * Parse a single Struct filter condition.
     *
     * Returns:
     *
     *     [
     *         field,
     *         operator,
     *         value,
     *         logic
     *     ]
     */
    private function parseFilterCondition(
        string $filter,
        string $logic = 'AND'
    ): array {

        $filter =
            trim(
                $filter
            );

        /*
         * Remove wrapping parentheses.
         */
        while (
            strlen($filter) >= 2 &&
            $filter[0] === '(' &&
            $filter[strlen($filter) - 1] === ')' &&
            $this->hasMatchingOuterParentheses($filter)
        ) {

            $filter =
                trim(
                    substr(
                        $filter,
                        1,
                        -1
                    )
                );
        }

        foreach (
            self::FILTER_OPERATORS as $operator
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
                $logic,
            ];
        }

        throw new \RuntimeException(
            'Invalid filter syntax. Expected field/operator/value.'
        );
    }

    /**
     * Parse a PLM filter into Struct filter conditions.
     *
     * Supports:
     *
     *     field=value
     *     field=value AND other=value
     *     (field=value) AND (other=value)
     *
     * OR is supported as well.
     */
    private function parseFilters(
        string $filter
    ): array {

        $filter =
            trim(
                $filter
            );

        if ($filter === '') {
            throw new \RuntimeException(
                'Filter must not be empty.'
            );
        }

        $parts = [];
        $operators = [];

        $buffer = '';
        $depth = 0;
        $length = strlen($filter);

        for (
            $i = 0;
            $i < $length;
            $i++
        ) {

            $char =
                $filter[$i];

            if ($char === '(') {

                $depth++;
                $buffer .= $char;

                continue;
            }

            if ($char === ')') {

                if ($depth > 0) {
                    $depth--;
                }

                $buffer .= $char;

                continue;
            }

            if ($depth === 0) {

                $remaining =
                    substr(
                        $filter,
                        $i
                    );

                if (
                    preg_match(
                        '/^\s+(AND|OR)\s+/i',
                        $remaining,
                        $match
                    )
                ) {

                    $parts[] =
                        trim(
                            $buffer
                        );

                    $operators[] =
                        strtoupper(
                            $match[1]
                        );

                    $buffer = '';

                    $i +=
                        strlen(
                            $match[0]
                        ) - 1;

                    continue;
                }
            }

            $buffer .= $char;
        }

        $buffer =
            trim(
                $buffer
            );

        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        if (empty($parts)) {
            throw new \RuntimeException(
                'Invalid filter syntax.'
            );
        }

        if (
            count($operators) !==
            count($parts) - 1
        ) {
            throw new \RuntimeException(
                'Invalid filter logic.'
            );
        }

        $conditions = [];

        foreach (
            $parts as $index => $part
        ) {

            $logic =
                $index === 0
                    ? 'AND'
                    : (
                        $operators[$index - 1]
                        ?? 'AND'
                    );

            $conditions[] =
                $this->parseFilterCondition(
                    $part,
                    $logic
                );
        }

        return $conditions;
    }

    /**
     * Parse one filter.
     *
     * Kept for callers which expect a single
     * condition.
     */
    private function parseFilter(
        string $filter
    ): array {

        $filters =
            $this->parseFilters(
                $filter
            );

        if (count($filters) !== 1) {
            throw new \RuntimeException(
                'Expected exactly one filter condition.'
            );
        }

        return $filters[0];
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

        /*
         * Remove PLM _pk from the actual Struct columns.
         */
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

        $parsedFilters = [];

        if (
            $filter !== null &&
            trim($filter) !== ''
        ) {

            $parsedFilters =
                $this->parseFilters(
                    $filter
                );

            foreach (
                $parsedFilters as $parsedFilter
            ) {

                $filterField =
                    $parsedFilter[0];

                if (
                    $filterField ===
                    self::PRIMARY_KEY_FIELD
                ) {

                    /*
                     * _pk is deliberately not passed
                     * to SearchConfig.
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

        if (!empty($parsedFilters)) {

            $structConfig['filter'] = [];

            foreach (
                $parsedFilters as $parsedFilter
            ) {

                $structConfig['filter'][] =
                    $parsedFilter;
            }
        }

        $search =
            new \dokuwiki\plugin\struct\meta\SearchConfig(
                $structConfig
            );

        $rows =
            $search->getRows();

        return [
            'search' =>
                $search,

            'rows' =>
                $rows,
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

    /**
     * Determine whether a Struct Column is a Lookup.
     */
    public function isLookupColumn(
        $column
    ): bool {

        if (
            !is_object($column) ||
            !method_exists(
                $column,
                'getType'
            )
        ) {
            return false;
        }

        return
            $column->getType()
            instanceof \dokuwiki\plugin\struct\types\Lookup;
    }

    /**
     * Return the referenced Struct RID(s) of a Lookup Value.
     *
     * Struct Lookup values can arrive in several forms.
     *
     * Typical value:
     *
     *     ["[\"\",1]","PRD-MC1210F"]
     *
     * The outer value is JSON and the first element is
     * itself JSON:
     *
     *     ["",1]
     *
     * The second element of the inner array is the
     * referenced Struct RID.
     */
    public function getLookupPrimaryKeys(
        $value
    ): array {

        if (
            !is_object($value) ||
            !method_exists(
                $value,
                'getValue'
            )
        ) {
            return [];
        }

        $raw =
            $value->getValue();

        /*
         * Normalize the outer value.
         *
         * getValue() may return:
         *
         *     string
         *     array
         */
        if (is_string($raw)) {

            $decoded =
                json_decode(
                    $raw,
                    true
                );

            if (
                json_last_error() !== JSON_ERROR_NONE ||
                !is_array($decoded)
            ) {
                return [];
            }

            $raw =
                $decoded;
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $result = [];

        foreach (
            $raw as $item
        ) {

            /*
             * Normal case:
             *
             *     $item = '["",1]'
             */
            if (is_string($item)) {

                $decoded =
                    json_decode(
                        $item,
                        true
                    );

                if (
                    json_last_error() === JSON_ERROR_NONE &&
                    is_array($decoded) &&
                    count($decoded) >= 2
                ) {

                    $rid =
                        $decoded[1];

                    if (
                        is_int($rid) ||
                        (
                            is_string($rid) &&
                            ctype_digit($rid)
                        )
                    ) {

                        $rid =
                            (int) $rid;

                        if ($rid > 0) {
                            $result[] =
                                $rid;
                        }
                    }
                }

                continue;
            }

            /*
             * Also support an already decoded lookup pair:
             *
             *     ['', 1]
             */
            if (
                is_array($item) &&
                count($item) >= 2
            ) {

                $rid =
                    $item[1];

                if (
                    is_int($rid) ||
                    (
                        is_string($rid) &&
                        ctype_digit($rid)
                    )
                ) {

                    $rid =
                        (int) $rid;

                    if ($rid > 0) {
                        $result[] =
                            $rid;
                    }
                }
            }
        }

        return array_values(
            array_unique(
                $result
            )
        );
    }

    /**
     * Get the access object for a Struct record.
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

    /**
     * Check whether an outer pair of parentheses matches
     * the complete expression.
     */
    private function hasMatchingOuterParentheses(
        string $value
    ): bool {

        $depth = 0;
        $length = strlen($value);

        for (
            $i = 0;
            $i < $length;
            $i++
        ) {

            if ($value[$i] === '(') {

                $depth++;

            } elseif ($value[$i] === ')') {

                $depth--;

                if (
                    $depth === 0 &&
                    $i < $length - 1
                ) {
                    return false;
                }
            }

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }
}