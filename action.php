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
         * ---------------------------------------------------------
         * TABLE FILTER
         * ---------------------------------------------------------
         */

        $table =
            $INPUT->str(
                'plm_filter_table'
            );

        $field =
            $INPUT->str(
                'plm_filter_field'
            );

        /*
         * No table filter submitted.
         */
        if (
            $table !== null &&
            $table !== '' &&
            $field !== null &&
            $field !== ''
        ) {

            $this->processTableFilter(
                $table,
                $field,
                $INPUT->str(
                    'plm_filter_value'
                ) ?? ''
            );

            /*
             * processTableFilter() redirects and
             * therefore never returns.
             */
            return;
        }

        /*
         * ---------------------------------------------------------
         * POST / PLM FORM
         * ---------------------------------------------------------
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
     * Process a submitted table filter.
     *
     * Temporary parameters:
     *
     *     plm_filter_table
     *     plm_filter_field
     *     plm_filter_value
     *
     * are converted into the encoded PLM state.
     *
     * The browser is then redirected to:
     *
     *     ?plm=<encoded-state>
     */
    private function processTableFilter(
        string $table,
        string $field,
        string $value
    ): void {

        global $ID;

        /*
         * Validate table name.
         */
        if (!preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $table
        )) {
            return;
        }

        /*
         * Validate field name.
         */
        if (!preg_match(
            '/^[a-zA-Z0-9_.-]+$/',
            $field
        )) {
            return;
        }

        /*
         * Load existing PLM state.
         */
        $state =
            new PlmState();

        /*
         * Store the submitted filter.
         */
        $state->setFilterValue(
            $table,
            $field,
            $value
        );

        /*
         * Encode complete state.
         */
        $encoded =
            $state->encode();

        /*
         * Build clean URL for current page.
         *
         * Do NOT copy the old query parameters.
         */
        $url =
            wl(
                $ID,
                [],
                true
            );

        /*
         * Append only the encoded PLM state.
         */
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
     * Process a PLM form action.
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
         * REDIRECT
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
         * CREATE
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
         * UPDATE / DELETE require an existing record.
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
         * DELETE
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
         * UPDATE
         */
        $data =
            $struct->getDataArray(
                $access
            );

        $posted =
            $INPUT->post->arr(
                'plm_form'
            );

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