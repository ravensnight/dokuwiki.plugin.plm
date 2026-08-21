<?php

require_once __DIR__ . '/classes/PlmStruct.php';
require_once __DIR__ . '/classes/PlmState.php';
require_once __DIR__ . '/classes/PlmReference.php';

class action_plugin_plm extends DokuWiki_Action_Plugin
{
    public function register(
        Doku_Event_Handler $controller
    ) {
        $controller->register_hook(
            'DOKUWIKI_STARTED',
            'BEFORE',
            $this,
            'handleRequest'
        );
    }

    /**
     * Handle PLM requests before DokuWiki renders
     * the current page.
     */
    public function handleRequest(
        Doku_Event $event
    ) {
        global $INPUT;

        if (
            strtoupper(
                $INPUT->server->str(
                    'REQUEST_METHOD'
                )
            ) !== 'POST'
        ) {
            return;
        }

        /*
         * ---------------------------------------------------------
         * PLM TABLE ACTION
         * ---------------------------------------------------------
         */

        $tableAction =
            strtolower(
                trim(
                    $INPUT->post->str(
                        'plm_action'
                    )
                )
            );

        if ($tableAction === 'filter') {

            try {

                $this->processTableFilter();

            } catch (Throwable $e) {

                msg(
                    'PLM table: ' .
                    $e->getMessage(),
                    -1
                );
            }

            return;
        }

        if ($tableAction === 'create') {

            try {

                $this->processTableCreate();

            } catch (Throwable $e) {

                msg(
                    'PLM table: ' .
                    $e->getMessage(),
                    -1
                );
            }

            return;
        }

        if ($tableAction === 'delete') {

            try {

                $this->processTableDelete();

            } catch (Throwable $e) {

                msg(
                    'PLM table: ' .
                    $e->getMessage(),
                    -1
                );
            }

            return;
        }

        if ($tableAction === 'details') {

            try {

                $this->processTableDetails();

            } catch (Throwable $e) {

                msg(
                    'PLM table: ' .
                    $e->getMessage(),
                    -1
                );
            }

            return;
        }

        /*
         * ---------------------------------------------------------
         * PLM FORM
         * ---------------------------------------------------------
         */

        if (
            $INPUT->post->str(
                'plm_form_submit'
            ) !== '1'
        ) {
            return;
        }

        try {

            $this->processSubmit();

        } catch (Throwable $e) {

            msg(
                'PLM form: ' .
                $e->getMessage(),
                -1
            );
        }
    }

    /**
     * -------------------------------------------------------------
     * TABLE FILTER
     * -------------------------------------------------------------
     *
     * Store filters as:
     *
     *     context.filter.field
     *
     * The table name is used as the context.
     */
    private function processTableFilter(): void
    {
        global $INPUT, $ID;

        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $this->validateTableContext(
            $table
        );

        $filters =
            $INPUT->post->arr(
                'plm_filter'
            );

        if (!is_array($filters)) {
            $filters = [];
        }

        $state =
            new PlmState();

        $values = [];

        foreach (
            $filters as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (!$this->isValidStateName($field)) {
                continue;
            }

            if (
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $value =
                (string) $value;

            if ($value === '') {
                continue;
            }

            $values[$field] =
                $value;
        }

        $state->setScope(
            $table,
            'filter',
            $values
        );

        $encoded =
            $state->encode();

        $url =
            wl(
                $ID,
                [],
                true
            );

        if ($encoded !== '') {

            $separator =
                str_contains(
                    $url,
                    '?'
                )
                    ? '&'
                    : '?';

            $url .=
                $separator .
                'plm=' .
                rawurlencode(
                    $encoded
                );
        }

        send_redirect(
            $url
        );

        exit;
    }

    /**
     * -------------------------------------------------------------
     * TABLE CREATE
     * -------------------------------------------------------------
     */
    private function processTableCreate(): void
    {
        global $INPUT, $ID;

        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $this->validateTableContext(
            $table
        );

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($schema === '') {
            throw new \RuntimeException(
                'No PLM schema specified.'
            );
        }

        $posted =
            $INPUT->post->arr(
                'plm_create'
            );

        if (!is_array($posted)) {
            $posted = [];
        }

        $struct =
            new PlmStruct();

        $schemaObject =
            new \dokuwiki\plugin\struct\meta\Schema(
                $schema
            );

        if (!$schemaObject->isEditable()) {
            throw new \RuntimeException(
                'You are not allowed to edit this Struct schema.'
            );
        }

        $access =
            $struct->newGlobalAccess(
                $schema
            );

        $data =
            $struct->getDataArray(
                $access
            );

        foreach (
            $posted as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (!$this->isValidStructField($field)) {
                continue;
            }

            if (
                $field ===
                PlmStruct::PRIMARY_KEY_FIELD
            ) {
                continue;
            }

            if (
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $data[$field] =
                (string) $value;
        }

        $struct->save(
            $access,
            $data
        );

        $data =
            $struct->getDataArray(
                $access
            );

        /*
         * The newly created RID is not necessarily part of
         * the returned data array.
         */
        if (
            method_exists(
                $access,
                'getRid'
            )
        ) {
            $data[
                PlmStruct::PRIMARY_KEY_FIELD
            ] =
                $access->getRid();
        }

        $this->redirect(
            'create',
            $data,
            $ID
        );
    }

    /**
     * -------------------------------------------------------------
     * TABLE DELETE
     * -------------------------------------------------------------
     */
    private function processTableDelete(): void
    {
        global $INPUT, $ID;

        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $this->validateTableContext(
            $table
        );

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($schema === '') {
            throw new \RuntimeException(
                'No PLM schema specified.'
            );
        }

        $posted =
            $INPUT->post->arr(
                'plm_delete'
            );

        if (!is_array($posted)) {
            throw new \RuntimeException(
                'No PLM delete field specified.'
            );
        }

        if (count($posted) !== 1) {
            throw new \RuntimeException(
                'PLM delete requires exactly one field.'
            );
        }

        $deleteField =
            array_key_first(
                $posted
            );

        $deleteValue =
            $posted[$deleteField];

        if (!is_string($deleteField)) {
            throw new \RuntimeException(
                'Invalid PLM delete field.'
            );
        }

        if (
            $deleteField !==
            PlmStruct::PRIMARY_KEY_FIELD &&
            !$this->isValidStructField(
                $deleteField
            )
        ) {
            throw new \RuntimeException(
                'Invalid PLM delete field.'
            );
        }

        if (
            is_array($deleteValue) ||
            is_object($deleteValue)
        ) {
            throw new \RuntimeException(
                'Invalid PLM delete value.'
            );
        }

        $deleteValue =
            trim(
                (string) $deleteValue
            );

        if ($deleteValue === '') {
            throw new \RuntimeException(
                'PLM delete value must not be empty.'
            );
        }

        $struct =
            new PlmStruct();

        $schemaObject =
            new \dokuwiki\plugin\struct\meta\Schema(
                $schema
            );

        if (!$schemaObject->isEditable()) {
            throw new \RuntimeException(
                'You are not allowed to edit this Struct schema.'
            );
        }

        $filterValue =
            $this->escapeStructFilterValue(
                $deleteValue
            );

        $filter =
            $deleteField .
            '=' .
            $filterValue;

        $record =
            $struct->findOne(
                $schema,
                $filter
            );

        if ($record === null) {
            throw new \RuntimeException(
                'No Struct record found for the given delete value.'
            );
        }

        $access =
            $struct->getAccessForRecord(
                $schema,
                $record['pid'],
                $record['rid']
            );

        $data =
            $struct->getDataArray(
                $access
            );

        $data[
            PlmStruct::PRIMARY_KEY_FIELD
        ] =
            $record['rid'];

        $struct->delete(
            $access
        );

        $this->redirect(
            'delete',
            $data,
            $ID
        );
    }

    /**
     * -------------------------------------------------------------
     * TABLE DETAILS
     * -------------------------------------------------------------
     *
     * Open a configured details target for the current
     * Struct row.
     */
    private function processTableDetails(): void
    {
        global $INPUT, $ID;

        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $this->validateTableContext(
            $table
        );

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($schema === '') {
            throw new \RuntimeException(
                'No PLM schema specified.'
            );
        }

        $posted =
            $INPUT->post->arr(
                'plm_details'
            );

        if (!is_array($posted)) {
            throw new \RuntimeException(
                'No PLM details row specified.'
            );
        }

        $struct =
            new PlmStruct();

        $rid =
            $posted[
                PlmStruct::PRIMARY_KEY_FIELD
            ] ?? '';

        if (
            is_array($rid) ||
            is_object($rid)
        ) {
            throw new \RuntimeException(
                'Invalid PLM details primary key.'
            );
        }

        $rid =
            trim(
                (string) $rid
            );

        if ($rid === '') {
            throw new \RuntimeException(
                'PLM details requires a primary key.'
            );
        }

        $record =
            $struct->findOne(
                $schema,
                PlmStruct::PRIMARY_KEY_FIELD .
                '=' .
                $this->escapeStructFilterValue(
                    $rid
                )
            );

        if ($record === null) {
            throw new \RuntimeException(
                'No Struct record found for the given details row.'
            );
        }

        $access =
            $struct->getAccessForRecord(
                $schema,
                $record['pid'],
                $record['rid']
            );

        $data =
            $struct->getDataArray(
                $access
            );

        /*
         * Always use the authoritative RID returned by Struct.
         */
        $data[
            PlmStruct::PRIMARY_KEY_FIELD
        ] =
            $record['rid'];

        /*
         * Store the complete current row in PLM state.
         */
        $state =
            new PlmState();

        $current = [];

        foreach (
            $data as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (
                !$this->isValidStructField(
                    $field
                ) &&
                $field !==
                PlmStruct::PRIMARY_KEY_FIELD
            ) {
                continue;
            }

            if (
                $value === null ||
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $value =
                (string) $value;

            if ($value === '') {
                continue;
            }

            $current[$field] =
                $value;
        }

        $state->setScope(
            $table,
            'current',
            $current
        );

        $redirects =
            $INPUT->post->arr(
                'plm_redirects'
            );

        if (!is_array($redirects)) {
            $redirects = [];
        }

        $target =
            trim(
                $redirects['details'] ?? ''
            );

        if ($target === '') {
            throw new \RuntimeException(
                'No PLM details redirect specified.'
            );
        }

        /*
         * Details has a real Struct record available.
         *
         * Resolve normal current-row references through
         * PlmReference. The state remains available for
         * %table.current.field references.
         */
        $reference =
            new PlmReference(
                $state,
                $struct
            );

        $this->setReferenceRowFromRecord(
            $reference,
            $struct,
            $schema,
            $record
        );

        $target =
            $reference->expand(
                $target,
                false
            );

        $this->sendTargetRedirectWithState(
            $target,
            $ID,
            $state
        );
    }

    /**
     * Set the current Struct row on a reference resolver.
     *
     * This obtains the Struct column indexes through a normal
     * Struct search and then locates the authoritative RID.
     */
    private function setReferenceRowFromRecord(
        PlmReference $reference,
        PlmStruct $struct,
        string $schema,
        array $record
    ): void {

        $result =
            $struct->search(
                $schema,
                []
            );

        $search =
            $result['search'];

        $rows =
            $result['rows'];

        $fieldIndexes = [];

        foreach (
            $search->getColumns()
            as $index => $column
        ) {
            $fieldIndexes[
                $column->getLabel()
            ] =
                $index;
        }

        $rids =
            $search->getRids();

        $matchedRow = null;

        foreach (
            $rows as $index => $row
        ) {

            if (
                (int) (
                    $rids[$index] ?? 0
                ) ===
                (int) $record['rid']
            ) {
                $matchedRow =
                    $row;

                break;
            }
        }

        /*
         * If the full search did not expose the row,
         * leave the resolver without a physical row.
         *
         * State references still work independently.
         */
        if ($matchedRow === null) {
            $reference->clearRow();
            return;
        }

        $reference->setRow(
            $matchedRow,
            $fieldIndexes
        );
    }

    /**
     * Escape a value used in a Struct filter.
     */
    private function escapeStructFilterValue(
        string $value
    ): string {

        return str_replace(
            [
                '\\',
                '*',
                '~',
                '[',
                ']',
            ],
            [
                '\\\\',
                '\\*',
                '\\~',
                '\\[',
                '\\]',
            ],
            $value
        );
    }

    /**
     * -------------------------------------------------------------
     * NORMAL PLM FORM
     * -------------------------------------------------------------
     */
    private function processSubmit(): void
    {
        global $INPUT, $ID;

        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        $action =
            strtolower(
                trim(
                    $INPUT->post->str(
                        'plm_action'
                    )
                )
            );

        if (!in_array(
            $action,
            [
                'create',
                'update',
                'delete',
                'redirect'
            ],
            true
        )) {
            throw new \RuntimeException(
                'Invalid PLM action.'
            );
        }

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($schema === '') {
            throw new \RuntimeException(
                'No PLM schema specified.'
            );
        }

        $filter =
            trim(
                $INPUT->post->str(
                    'plm_filter'
                )
            );

        if ($filter === '') {
            $filter = null;
        }

        /*
         * ---------------------------------------------------------
         * REDIRECT
         * ---------------------------------------------------------
         */

        if ($action === 'redirect') {

            $posted =
                $INPUT->post->arr(
                    'plm_form'
                );

            $redirects =
                $INPUT->post->arr(
                    'plm_redirects'
                );

            if (!is_array($posted)) {
                $posted = [];
            }

            if (!is_array($redirects)) {
                $redirects = [];
            }

            $this->redirectForm(
                $posted,
                $redirects,
                $ID
            );

            return;
        }

        $struct =
            new PlmStruct();

        $schemaObject =
            new \dokuwiki\plugin\struct\meta\Schema(
                $schema
            );

        if (!$schemaObject->isEditable()) {
            throw new \RuntimeException(
                'You are not allowed to edit this Struct schema.'
            );
        }

        /*
         * ---------------------------------------------------------
         * CREATE
         * ---------------------------------------------------------
         */

        if ($action === 'create') {

            if ($filter !== null) {
                throw new \RuntimeException(
                    'Create action cannot be used for an existing record.'
                );
            }

            $access =
                $struct->newGlobalAccess(
                    $schema
                );

            $data =
                $struct->getDataArray(
                    $access
                );

            $posted =
                $INPUT->post->arr(
                    'plm_form'
                );

            if (!is_array($posted)) {
                $posted = [];
            }

            foreach (
                $posted as $field => $value
            ) {

                if (!is_string($field)) {
                    continue;
                }

                if (
                    $field ===
                    PlmStruct::PRIMARY_KEY_FIELD
                ) {
                    continue;
                }

                if (
                    is_array($value) ||
                    is_object($value)
                ) {
                    continue;
                }

                $data[$field] =
                    (string) $value;
            }

            $struct->save(
                $access,
                $data
            );

            $data =
                $struct->getDataArray(
                    $access
                );

            if (
                method_exists(
                    $access,
                    'getRid'
                )
            ) {
                $data[
                    PlmStruct::PRIMARY_KEY_FIELD
                ] =
                    $access->getRid();
            }

            $this->redirect(
                $action,
                $data,
                $ID
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * EXISTING RECORD
         * ---------------------------------------------------------
         */

        if ($filter === null) {
            throw new \RuntimeException(
                ucfirst($action) .
                ' action requires an existing record.'
            );
        }

        $record =
            $struct->findOne(
                $schema,
                $filter
            );

        if ($record === null) {
            throw new \RuntimeException(
                'No Struct record found for the given filter.'
            );
        }

        $access =
            $struct->getAccessForRecord(
                $schema,
                $record['pid'],
                $record['rid']
            );

        /*
         * ---------------------------------------------------------
         * DELETE
         * ---------------------------------------------------------
         */

        if ($action === 'delete') {

            $data =
                $struct->getDataArray(
                    $access
                );

            $data[
                PlmStruct::PRIMARY_KEY_FIELD
            ] =
                $record['rid'];

            $struct->delete(
                $access
            );

            $this->redirect(
                $action,
                $data,
                $ID
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * UPDATE
         * ---------------------------------------------------------
         */

        $data =
            $struct->getDataArray(
                $access
            );

        $posted =
            $INPUT->post->arr(
                'plm_form'
            );

        if (!is_array($posted)) {
            $posted = [];
        }

        foreach (
            $posted as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (
                $field ===
                PlmStruct::PRIMARY_KEY_FIELD
            ) {
                continue;
            }

            if (
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $data[$field] =
                (string) $value;
        }

        $struct->save(
            $access,
            $data
        );

        $data =
            $struct->getDataArray(
                $access
            );

        $data[
            PlmStruct::PRIMARY_KEY_FIELD
        ] =
            $record['rid'];

        $this->redirect(
            $action,
            $data,
            $ID
        );
    }

    /**
     * Redirect from a normal PLM form.
     *
     * Submitted form values are exposed through a temporary
     * PLM state context:
     *
     *     __redirect.current.field
     *
     * The target is then expanded centrally through
     * PlmReference.
     */
    private function redirectForm(
        array $posted,
        array $redirects,
        string $defaultTarget
    ): void {

        $target =
            trim(
                $redirects['redirect'] ?? ''
            );

        if ($target === '') {
            $target =
                $defaultTarget;
        }

        $state =
            new PlmState();

        $values = [];

        foreach (
            $posted as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (!$this->isValidStructField($field)) {
                continue;
            }

            $values[$field] =
                $this->stringifyFormValue(
                    $value
                );
        }

        $state->setScope(
            '__redirect',
            'current',
            $values
        );

        /*
         * Convert legacy/current form references:
         *
         *     $field
         *
         * into the central state syntax:
         *
         *     %__redirect.current.field
         *
         * This keeps all actual reference expansion inside
         * PlmReference.
         */
        $target =
            $this->convertFormReferences(
                $target
            );

        $reference =
            new PlmReference(
                $state,
                new PlmStruct()
            );

        $target =
            $reference->expand(
                $target,
                false
            );

        $this->sendTargetRedirect(
            $target,
            $defaultTarget
        );
    }

    /**
     * Convert legacy $field references to the temporary
     * redirect state context.
     */
    private function convertFormReferences(
        string $target
    ): string {

        return
            preg_replace_callback(
                '/\$([a-zA-Z0-9_-]+(?:\._pk)?)/',
                function ($match) {

                    return
                        '%__redirect.current.' .
                        $match[1];
                },
                $target
            );
    }

    /**
     * Convert a submitted form value to a string.
     */
    private function stringifyFormValue(
        $value
    ): string {

        if (is_array($value)) {

            $values = [];

            foreach ($value as $item) {

                if (is_array($item)) {

                    $values[] =
                        implode(
                            ',',
                            array_map(
                                'strval',
                                $item
                            )
                        );

                } else {

                    $values[] =
                        (string) $item;
                }
            }

            return implode(
                ',',
                $values
            );
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Redirect after CREATE / UPDATE / DELETE.
     *
     * Struct data is exposed through the same temporary
     * PLM state context used by form redirects.
     *
     * Example:
     *
     *     $ipn
     *
     * is internally resolved through:
     *
     *     %__redirect.current.ipn
     *
     * and therefore ultimately by PlmReference.
     */
    private function redirect(
        string $action,
        array $data,
        string $defaultTarget
    ): void {

        global $INPUT;

        $redirects =
            $INPUT->post->arr(
                'plm_redirects'
            );

        if (!is_array($redirects)) {
            $redirects = [];
        }

        $target =
            trim(
                $redirects[$action] ?? ''
            );

        if ($target === '') {
            $target =
                $defaultTarget;
        }

        $state =
            new PlmState();

        $values = [];

        foreach (
            $data as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (
                $field !==
                PlmStruct::PRIMARY_KEY_FIELD &&
                !$this->isValidStructField(
                    $field
                )
            ) {
                continue;
            }

            if (
                $value === null ||
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $values[$field] =
                (string) $value;
        }

        $state->setScope(
            '__redirect',
            'current',
            $values
        );

        /*
         * Route all normal $field references through the
         * central resolver.
         */
        $target =
            $this->convertFormReferences(
                $target
            );

        $reference =
            new PlmReference(
                $state,
                new PlmStruct()
            );

        $target =
            $reference->expand(
                $target,
                false
            );

        $this->sendTargetRedirect(
            $target,
            $defaultTarget
        );
    }

    /**
     * Append form parameters to a redirect.
     *
     * Retained for compatibility with existing PLM forms.
     */
    private function appendFormParameters(
        string $target,
        array $posted
    ): string {

        $parts =
            explode(
                '?',
                $target,
                2
            );

        $page =
            $parts[0];

        $query =
            $parts[1] ?? '';

        $existing = [];

        if ($query !== '') {

            parse_str(
                $query,
                $existing
            );
        }

        foreach (
            $posted as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (!preg_match(
                '/^[a-zA-Z_][a-zA-Z0-9_-]*$/',
                $field
            )) {
                continue;
            }

            if (array_key_exists(
                $field,
                $existing
            )) {
                continue;
            }

            $existing[$field] =
                $this->stringifyFormValue(
                    $value
                );
        }

        if (empty($existing)) {
            return $page;
        }

        return
            $page .
            '?' .
            http_build_query(
                $existing,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    /**
     * Convert a PLM target into a DokuWiki URL.
     */
    private function sendTargetRedirect(
        string $target,
        string $defaultTarget
    ): void {

        $target =
            trim(
                $target
            );

        if ($target === '') {
            $target =
                $defaultTarget;
        }

        $parts =
            explode(
                '?',
                $target,
                2
            );

        $page =
            trim(
                $parts[0]
            );

        $query =
            $parts[1] ?? '';

        $page =
            cleanID(
                $page
            );

        if ($page === '') {

            $fallbackParts =
                explode(
                    '?',
                    $defaultTarget,
                    2
                );

            $page =
                cleanID(
                    trim(
                        $fallbackParts[0]
                    )
                );

            $query =
                $fallbackParts[1] ?? '';
        }

        $params = [];

        if ($query !== '') {

            parse_str(
                $query,
                $params
            );

            if (!is_array($params)) {
                $params = [];
            }
        }

        $url =
            wl(
                $page,
                $params,
                true
            );

        send_redirect(
            $url
        );

        exit;
    }

    /**
     * Redirect while preserving the current PLM state.
     */
    private function sendTargetRedirectWithState(
        string $target,
        string $defaultTarget,
        PlmState $state
    ): void {

        $target =
            trim(
                $target
            );

        if ($target === '') {
            $target =
                $defaultTarget;
        }

        $parts =
            explode(
                '?',
                $target,
                2
            );

        $page =
            cleanID(
                trim(
                    $parts[0]
                )
            );

        $query =
            $parts[1] ?? '';

        if ($page === '') {

            $fallbackParts =
                explode(
                    '?',
                    $defaultTarget,
                    2
                );

            $page =
                cleanID(
                    trim(
                        $fallbackParts[0]
                    )
                );

            $query =
                $fallbackParts[1] ?? '';
        }

        $params = [];

        if ($query !== '') {

            parse_str(
                $query,
                $params
            );

            if (!is_array($params)) {
                $params = [];
            }
        }

        /*
         * Preserve the complete PLM state.
         */
        $encoded =
            $state->encode();

        if ($encoded !== '') {
            $params['plm'] =
                $encoded;
        }

        $url =
            wl(
                $page,
                $params,
                true
            );

        send_redirect(
            $url
        );

        exit;
    }

    /**
     * Validate a Table context name.
     */
    private function validateTableContext(
        string $table
    ): void {

        if ($table === '') {
            throw new \RuntimeException(
                'No PLM table specified.'
            );
        }

        if (!$this->isValidStateName($table)) {
            throw new \RuntimeException(
                'Invalid PLM table name.'
            );
        }
    }

    /**
     * Validate a State context/scope/field name.
     */
    private function isValidStateName(
        string $name
    ): bool {

        return preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $name
        ) === 1;
    }

    /**
     * Validate a Struct field name.
     */
    private function isValidStructField(
        string $field
    ): bool {

        return preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $field
        ) === 1;
    }
}
