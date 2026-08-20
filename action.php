<?php

require_once __DIR__ . '/classes/PlmStruct.php';
require_once __DIR__ . '/classes/PlmState.php';

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

        /*
         * Only POST requests are relevant.
         */
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
         *
         * Table actions:
         *
         *     plm_action=filter
         *     plm_action=create
         *
         * Table filter fields:
         *
         *     plm_filter[field]
         *
         * Table create fields:
         *
         *     plm_create[field]
         *
         * The namespaces are deliberately different.
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

        /*
         * ---------------------------------------------------------
         * PLM FORM
         * ---------------------------------------------------------
         *
         * Standalone forms continue to use:
         *
         *     plm_form_submit=1
         *     plm_form[field]
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
     * Expected POST:
     *
     *     plm_action=filter
     *     plm_table=<table name>
     *     plm_filter[field]=<value>
     */
    private function processTableFilter(): void
    {
        global $INPUT, $ID;

        /*
         * Security token.
         */
        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        /*
         * ---------------------------------------------------------
         * TABLE
         * ---------------------------------------------------------
         */

        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        if ($table === '') {
            throw new \RuntimeException(
                'No PLM table specified.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $table
        )) {
            throw new \RuntimeException(
                'Invalid PLM table name.'
            );
        }

        /*
         * ---------------------------------------------------------
         * FILTERS
         * ---------------------------------------------------------
         */

        $filters =
            $INPUT->post->arr(
                'plm_filter'
            );

        if (!is_array($filters)) {
            $filters = [];
        }

        $state =
            new PlmState();

        /*
         * Store submitted filters.
         *
         * Empty values are stored deliberately so
         * an existing filter can be cleared.
         */
        foreach (
            $filters as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (!preg_match(
                '/^[a-zA-Z0-9_.-]+$/',
                $field
            )) {
                continue;
            }

            if (
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $state->setFilterValue(
                $table,
                $field,
                (string) $value
            );
        }

        /*
         * ---------------------------------------------------------
         * ENCODE STATE
         * ---------------------------------------------------------
         */

        $encoded =
            $state->encode();

        /*
         * Build clean URL for current page.
         */
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
     *
     * Expected POST:
     *
     *     plm_action=create
     *     plm_table=<table name>
     *     plm_schema=<schema>
     *     plm_create[field]=<value>
     *     plm_redirects[create]=<target>
     */
    private function processTableCreate(): void
    {
        global $INPUT, $ID;

        /*
         * Security token.
         */
        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        /*
         * ---------------------------------------------------------
         * TABLE
         * ---------------------------------------------------------
         *
         * The table name is not required for creating
         * the Struct record itself, but it is required
         * as part of the table POST contract.
         */
        $table =
            trim(
                $INPUT->post->str(
                    'plm_table'
                )
            );

        if ($table === '') {
            throw new \RuntimeException(
                'No PLM table specified.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $table
        )) {
            throw new \RuntimeException(
                'Invalid PLM table name.'
            );
        }

        /*
         * ---------------------------------------------------------
         * SCHEMA
         * ---------------------------------------------------------
         */

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

        /*
         * ---------------------------------------------------------
         * CREATE DATA
         * ---------------------------------------------------------
         *
         * IMPORTANT:
         *
         * Only plm_create is read here.
         *
         * plm_filter is completely independent.
         */

        $posted =
            $INPUT->post->arr(
                'plm_create'
            );

        if (!is_array($posted)) {
            $posted = [];
        }

        /*
         * Struct.
         */
        $struct =
            new PlmStruct();

        /*
         * Check schema permissions.
         */
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
         * NEW RECORD
         * ---------------------------------------------------------
         */

        $access =
            $struct->newGlobalAccess(
                $schema
            );

        $data =
            $struct->getDataArray(
                $access
            );

        /*
         * Copy submitted CREATE fields only.
         */
        foreach (
            $posted as $field => $value
        ) {

            if (!is_string($field)) {
                continue;
            }

            if (!preg_match(
                '/^[a-zA-Z0-9_.-]+$/',
                $field
            )) {
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

        /*
         * Save.
         */
        $struct->save(
            $access,
            $data
        );

        /*
         * Read final values back.
         */
        $data =
            $struct->getDataArray(
                $access
            );

        /*
         * ---------------------------------------------------------
         * REDIRECT
         * ---------------------------------------------------------
         */

        $redirects =
            $INPUT->post->arr(
                'plm_redirects'
            );

        if (!is_array($redirects)) {
            $redirects = [];
        }

        $target =
            trim(
                $redirects['create'] ?? ''
            );

        if ($target === '') {
            $target =
                $ID;
        }

        $target =
            $this->expandRedirectTarget(
                $target,
                $data
            );

        $this->sendTargetRedirect(
            $target,
            $ID
        );
    }

    /**
     * Expand $field references in a redirect.
     */
    private function expandRedirectTarget(
        string $target,
        array $data
    ): string {

        return
            preg_replace_callback(
                '/\$([a-zA-Z_][a-zA-Z0-9_-]*)/',
                function ($match) use ($data) {

                    $field =
                        $match[1];

                    if (!array_key_exists(
                        $field,
                        $data
                    )) {
                        return '';
                    }

                    $value =
                        $data[$field];

                    if (is_array($value)) {

                        return implode(
                            ',',
                            $value
                        );
                    }

                    return (string) $value;
                },
                $target
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

        /*
         * Security token.
         */
        if (!checkSecurityToken()) {
            throw new \RuntimeException(
                'Invalid security token.'
            );
        }

        /*
         * Action.
         */
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

        /*
         * Schema.
         */
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

        /*
         * Filter.
         *
         * NOTE:
         *
         * Standalone PLM forms use plm_filter as
         * a plain string here.
         *
         * Table filters use plm_filter[field].
         */
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

        /*
         * Check schema permissions.
         */
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

                $data[$field] =
                    $value;
            }

            $struct->save(
                $access,
                $data
            );

            $data =
                $struct->getDataArray(
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
         * UPDATE / DELETE
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

            $data[$field] =
                $value;
        }

        $struct->save(
            $access,
            $data
        );

        $data =
            $struct->getDataArray(
                $access
            );

        $this->redirect(
            $action,
            $data,
            $ID
        );
    }

    /**
     * Redirect using submitted form values.
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

        preg_match_all(
            '/\$([a-zA-Z_][a-zA-Z0-9_-]*)/',
            $target,
            $matches
        );

        $referencedFields =
            array_values(
                array_unique(
                    $matches[1] ?? []
                )
            );

        if (!empty($referencedFields)) {

            foreach (
                $referencedFields as $field
            ) {

                $value =
                    $posted[$field] ?? '';

                $value =
                    $this->stringifyFormValue(
                        $value
                    );

                $target =
                    str_replace(
                        '$' . $field,
                        rawurlencode($value),
                        $target
                    );
            }

            $this->sendTargetRedirect(
                $target,
                $defaultTarget
            );

            return;
        }

        $target =
            $this->appendFormParameters(
                $target,
                $posted
            );

        $this->sendTargetRedirect(
            $target,
            $defaultTarget
        );
    }

    /**
     * Append form parameters to a redirect.
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

        $target =
            preg_replace_callback(
                '/\$([a-zA-Z_][a-zA-Z0-9_-]*)/',
                function ($match) use ($data) {

                    $field =
                        $match[1];

                    if (!array_key_exists(
                        $field,
                        $data
                    )) {
                        return '';
                    }

                    $value =
                        $data[$field];

                    if (is_array($value)) {
                        return implode(
                            ',',
                            $value
                        );
                    }

                    return (string) $value;
                },
                $target
            );

        $this->sendTargetRedirect(
            $target,
            $defaultTarget
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
    }
}