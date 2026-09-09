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

        $action =
            strtolower(
                trim(
                    $INPUT->post->str(
                        'plm_action'
                    )
                )
            );

        if ($action === '') {
            return;
        }

        /*
         * ---------------------------------------------------------
         * PLM TABLE ACTIONS
         *
         * Table details/filter/delete must be detected from their
         * table-specific POST fields.
         *
         * This is intentionally done BEFORE plm_form_submit,
         * because a table form may also contain plm_form_submit
         * due to the surrounding HTML structure.
         * ---------------------------------------------------------
         */

        /*
         * TABLE DETAILS
         */
        if (
            $action === 'details' &&
            $INPUT->post->has('plm_table') &&
            $INPUT->post->has('plm_details')
        ) {

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
         * TABLE FILTER
         */
        if (
            $action === 'filter' &&
            $INPUT->post->has('plm_table') &&
            $INPUT->post->has('plm_filter')
        ) {

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

        /*
         * TABLE DELETE
         *
         * A form delete also uses plm_action=delete, therefore
         * the presence of the table-specific plm_delete payload
         * is used to distinguish the two cases.
         */
        if (
            $action === 'delete' &&
            $INPUT->post->has('plm_table') &&
            $INPUT->post->has('plm_delete')
        ) {

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

        /*
         * TABLE CREATE
         *
         * Table create is distinct from form create by its
         * table context.
         */
        if (
            $action === 'create' &&
            $INPUT->post->has('plm_table') &&
            $INPUT->post->has('plm_create') &&
            !$INPUT->post->has('plm_form')
        ) {

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

        /*
         * ---------------------------------------------------------
         * PLM FORM ACTION
         *
         * At this point the request was not identified as a
         * table-specific action.
         * ---------------------------------------------------------
         */

        if (
            $INPUT->post->has(
                'plm_form_submit'
            )
        ) {

            try {

                $this->processForm();

            } catch (Throwable $e) {

                msg(
                    'PLM form: ' .
                    $e->getMessage(),
                    -1
                );
            }

            return;
        }
    }

    /**
     * -------------------------------------------------------------
     * TABLE FILTER
     * -------------------------------------------------------------
     */
    private function processTableFilter(): void
    {
        global $INPUT;

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($table === '') {
            throw new \RuntimeException(
                'No PLM table specified.'
            );
        }

        if ($schema === '') {
            throw new \RuntimeException(
                'No PLM schema specified.'
            );
        }

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

        $stateValues = [];

        foreach (
            $filters as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (
                !$this->isValidStructField(
                    $field
                )
            ) {
                continue;
            }

            if (
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $stateValues[$field] =
                (string) $value;
        }

        $state->setScope(
            $table,
            'filter',
            $stateValues
        );

        $this->redirectToCurrentPage(
            $state
        );
    }

    /**
     * -------------------------------------------------------------
     * TABLE CREATE
     * -------------------------------------------------------------
     */
    private function processTableCreate(): void
    {
        global $INPUT;
        global $ID;

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($table === '') {
            throw new \RuntimeException(
                'No PLM table specified.'
            );
        }

        $this->validateTableContext(
            $table
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

            if (is_object($value)) {
                continue;
            }

            /*
             * Struct multi-value fields are submitted as arrays.
             *
             * Lookup + Multi-Value fields contain the raw Struct
             * lookup values (JSON encoded PID/RID pairs), so the
             * array must be passed to Struct unchanged.
             *
             * Non-multi fields still reject arrays as before.
             */
            if (is_array($value)) {

                if (
                    !$this->isMultiStructField(
                        $schemaObject,
                        $field
                    )
                ) {
                    continue;
                }

                $cleanValues = [];

                foreach ($value as $item) {

                    if (is_scalar($item)) {
                        $cleanValues[] =
                            (string) $item;
                    }
                }

                $data[$field] =
                    array_values($cleanValues);

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
        global $INPUT;
        global $ID;

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($table === '') {
            throw new \RuntimeException(
                'No PLM table specified.'
            );
        }

        $this->validateTableContext(
            $table
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
            $posted = [];
        }

        $rid =
            isset($posted['_pk'])
                ? (int) $posted['_pk']
                : 0;

        if ($rid <= 0) {
            throw new \RuntimeException(
                'No valid PLM record selected for deletion.'
            );
        }

        $struct =
            new PlmStruct();

        $record =
            $struct->findByPrimaryKey(
                $schema,
                (string) $rid
            );

        if ($record === null) {
            throw new \RuntimeException(
                'PLM record not found.'
            );
        }

        $access =
            $struct->getAccessForRecord(
                $schema,
                $record['pid'],
                $record['rid']
            );

        $struct->delete(
            $access
        );

        $this->redirect(
            'delete',
            [
                PlmStruct::PRIMARY_KEY_FIELD =>
                    $rid
            ],
            $ID
        );
    }

    /**
     * -------------------------------------------------------------
     * TABLE DETAILS
     * -------------------------------------------------------------
     */
    private function processTableDetails(): void
    {
        global $INPUT;

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        if ($table === '') {
            throw new \RuntimeException(
                'No PLM table specified.'
            );
        }

        $this->validateTableContext(
            $table
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
            $posted = [];
        }

        $rid =
            isset($posted['_pk'])
                ? (int) $posted['_pk']
                : 0;

        if ($rid <= 0) {
            throw new \RuntimeException(
                'No valid PLM record selected.'
            );
        }

        $redirects =
            $INPUT->post->arr(
                'plm_redirects'
            );

        if (!is_array($redirects)) {
            $redirects = [];
        }

        $target =
            isset($redirects['details'])
                ? trim(
                    (string) $redirects['details']
                )
                : '';

        if ($target === '') {
            throw new \RuntimeException(
                'No PLM details target specified.'
            );
        }

        $struct =
            new PlmStruct();

        $record =
            $struct->findByPrimaryKey(
                $schema,
                (string) $rid
            );

        if ($record === null) {
            throw new \RuntimeException(
                'PLM record not found.'
            );
        }

        $state =
            new PlmState();

        /*
         * Preserve an existing current scope and only replace
         * the primary key.
         */
        $current =
            $state->getScope(
                $table,
                'current'
            );

        if (!is_array($current)) {
            $current = [];
        }

        $current[
            PlmStruct::PRIMARY_KEY_FIELD
        ] =
            (string) $rid;

        $state->setScope(
            $table,
            'current',
            $current
        );

        $this->redirectToTarget(
            $target,
            $state
        );
    }

    /**
     * -------------------------------------------------------------
     * FORM
     * -------------------------------------------------------------
     */
    private function processForm(): void
    {
        global $INPUT;
        global $ID;

        $schema =
            trim(
                $INPUT->post->str(
                    'plm_schema'
                )
            );

        $filter =
            trim(
                $INPUT->post->str(
                    'plm_filter'
                )
            );

        $action =
            strtolower(
                trim(
                    $INPUT->post->str(
                        'plm_action'
                    )
                )
            );

        if ($schema === '') {
            throw new \RuntimeException(
                'No PLM schema specified.'
            );
        }

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

            if ($filter !== '') {
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
                'create',
                $data,
                $ID
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * FIND EXISTING RECORD
         * ---------------------------------------------------------
         */

        if ($filter === '') {
            throw new \RuntimeException(
                'No PLM record filter specified.'
            );
        }

        $record =
            $struct->findOne(
                $schema,
                $filter
            );

        if ($record === null) {
            throw new \RuntimeException(
                'PLM record not found.'
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
         * UPDATE
         * ---------------------------------------------------------
         */

        if ($action === 'update') {

            $posted =
                $INPUT->post->arr(
                    'plm_form'
                );

            if (!is_array($posted)) {
                $posted = [];
            }

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

            $this->redirect(
                'update',
                $record,
                $ID
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * DELETE
         * ---------------------------------------------------------
         */

        if ($action === 'delete') {

            $struct->delete(
                $access
            );

            $this->redirect(
                'delete',
                $record,
                $ID
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * REDIRECT
         * ---------------------------------------------------------
         */

        if ($action === 'redirect') {

            $redirect =
                $INPUT->post->str(
                    'plm_redirect'
                );

            $redirect =
                trim(
                    $redirect
                );

            if ($redirect === '') {
                throw new \RuntimeException(
                    'No PLM redirect specified.'
                );
            }

            $this->redirectToTarget(
                $redirect
            );

            return;
        }
    }

    /**
     * Validate the table context name.
     */
    private function validateTableContext(
        string $table
    ): void {

        if (
            !preg_match(
                '/^[a-zA-Z0-9_-]+$/',
                $table
            )
        ) {
            throw new \RuntimeException(
                'Invalid PLM table context.'
            );
        }
    }

    /**
     * Determine whether a Struct field accepts multiple values.
     */
    private function isMultiStructField(
        $schemaObject,
        string $field
    ): bool {

        $column =
            $schemaObject->findColumn(
                $field
            );

        if (
            !is_object($column) ||
            !method_exists(
                $column,
                'isMulti'
            )
        ) {
            return false;
        }

        return $column->isMulti();
    }

    /**
     * Validate a Struct field name.
     */
    private function isValidStructField(
        string $field
    ): bool {

        return (bool) preg_match(
            '/^[a-zA-Z0-9_.-]+$/',
            $field
        );
    }

    /**
     * Redirect to the current page while preserving
     * the supplied PLM state.
     */
    private function redirectToCurrentPage(
        ?PlmState $state = null
    ): void
    {
        global $ID;

        $this->redirect(
            '',
            [],
            $ID,
            $state
        );
    }

    /**
     * Redirect to an explicit target while preserving
     * the supplied PLM state.
     */
    private function redirectToTarget(
        string $target,
        ?PlmState $state = null
    ): void {

        global $ID;

        $target =
            trim(
                $target
            );

        if ($target === '') {
            $target = $ID;
        }

        if ($state === null) {
            $state =
                new PlmState();
        }

        $encodedState =
            $state->encode();

        $params = [];

        if ($encodedState !== '') {
            $params['plm'] =
                $encodedState;
        }

        send_redirect(
            wl(
                $target,
                $params
            )
        );
    }

    /**
     * Build the redirect after an action while preserving
     * the supplied PLM state.
     */
    private function redirect(
        string $action,
        array $data,
        string $page,
        ?PlmState $state = null
    ): void {

        global $ID;

        $page =
            trim(
                $page
            );

        if ($page === '') {
            $page = $ID;
        }

        if ($state === null) {
            $state =
                new PlmState();
        }

        $encodedState =
            $state->encode();

        $params = [];

        if ($encodedState !== '') {
            $params['plm'] =
                $encodedState;
        }

        $url =
            wl(
                $page,
                $params
            );

        send_redirect(
            $url
        );
    }
}