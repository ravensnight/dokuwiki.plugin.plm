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

        $filters =
            $INPUT->post->arr(
                'plm_filter'
            );

        if (!is_array($filters)) {
            $filters = [];
        }

        $state =
            new PlmState();

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

            if (
                $field ===
                PlmStruct::PRIMARY_KEY_FIELD
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
            !preg_match(
                '/^[a-zA-Z0-9_.-]+$/',
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

        $redirects =
            $INPUT->post->arr(
                'plm_redirects'
            );

        if (!is_array($redirects)) {
            $redirects = [];
        }

        $target =
            trim(
                $redirects['delete'] ?? ''
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

        /*
         * This is the IMPORTANT part:
         *
         * PlmForm has already expanded:
         *
         *     _pk=&_pk
         *
         * into:
         *
         *     _pk=123
         *
         * Therefore the POST handler does not need to know
         * anything about URL references.
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

                if ($field === '_pk') {
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
         * EXISTING RECORD
         * ---------------------------------------------------------
         */

        if ($filter === null) {
            throw new \RuntimeException(
                ucfirst($action) .
                ' action requires an existing record.'
            );
        }

        /*
         * All existing-record actions use the central
         * PlmStruct lookup.
         *
         * This includes:
         *
         *     _pk=123
         */
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

            /*
             * _pk is technical/read-only.
             */
            if (
                $field ===
                PlmStruct::PRIMARY_KEY_FIELD
            ) {
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

        /*
         * Make the technical RID available to
         * redirect placeholders.
         */
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