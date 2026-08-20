<?php

require_once __DIR__ . '/classes/PlmStruct.php';

class action_plugin_plm extends DokuWiki_Action_Plugin
{
    public function register(
        Doku_Event_Handler $controller
    ) {
        $controller->register_hook(
            'DOKUWIKI_STARTED',
            'BEFORE',
            $this,
            'handlePost'
        );
    }

    public function handlePost(
        Doku_Event $event
    ) {
        global $INPUT;

        if (
            strtoupper(
                $INPUT->server->str('REQUEST_METHOD')
            ) !== 'POST'
        ) {
            return;
        }

        if (
            $INPUT->post->str('plm_form_submit') !== '1'
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
     * Process a PLM action.
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

        if (
            !in_array(
                $action,
                [
                    'create',
                    'update',
                    'delete',
                    'redirect'
                ],
                true
            )
        ) {
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
         * ---------------------------------------------------------
         * REDIRECT
         * ---------------------------------------------------------
         *
         * Redirect does not modify the Struct record.
         *
         * It operates only on the submitted form values.
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
         * Validate schema permissions for actions
         * which modify Struct data.
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

            /*
             * Re-read data after saving so that
             * generated/default values are available
             * for redirect expansion.
             */
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

            /*
             * Read the values before deletion so they
             * can still be used in a redirect.
             */
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

        /*
         * Re-read the saved values for redirect
         * placeholder expansion.
         */
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
     *
     * Two modes exist.
     *
     * 1. No $field placeholder:
     *
     *    action: redirect "Open" :intern:plm:partview
     *
     *    All submitted plm_form fields are appended.
     *
     * 2. One or more $field placeholders:
     *
     *    action: redirect "Open" :intern:plm:partview?ipn=$ipn
     *
     *    Only fields referenced by $field are transferred.
     *
     * Technical hidden fields such as:
     *
     * plm_schema
     * plm_filter
     * security token
     *
     * are never transferred because only plm_form
     * is inspected.
     */
    private function redirectForm(
        array $posted,
        array $redirects,
        string $defaultTarget
    ): void {

        /*
         * Determine redirect target.
         */
        $target =
            trim(
                $redirects['redirect'] ?? ''
            );

        if ($target === '') {
            $target =
                $defaultTarget;
        }

        /*
         * Find explicit $field references.
         */
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

        /*
         * ---------------------------------------------------------
         * Explicit $field mode.
         * ---------------------------------------------------------
         *
         * Example:
         *
         * :intern:plm:partview?ipn=$ipn
         *
         * becomes:
         *
         * :intern:plm:partview?ipn=PRD
         */
        if (!empty($referencedFields)) {

            foreach (
                $referencedFields
                as $field
            ) {

                $value =
                    $posted[$field] ?? '';

                $value =
                    $this->stringifyFormValue(
                        $value
                    );

                /*
                 * Encode the value because it is being
                 * inserted into a URI.
                 */
                $encoded =
                    rawurlencode(
                        $value
                    );

                $target =
                    str_replace(
                        '$' . $field,
                        $encoded,
                        $target
                    );
            }

            $this->sendTargetRedirect(
                $target,
                $defaultTarget
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * No explicit $field references.
         * ---------------------------------------------------------
         *
         * Therefore all submitted form fields are
         * appended as query parameters.
         */
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
     * Append form values to a redirect target.
     *
     * Existing query parameters are preserved and
     * are not overwritten by form values.
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

            /*
             * Only sane URI parameter names.
             */
            if (!preg_match(
                '/^[a-zA-Z_][a-zA-Z0-9_-]*$/',
                $field
            )) {
                continue;
            }

            /*
             * Explicit target parameters win.
             */
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
     * Convert a submitted form value to a scalar
     * URI value.
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

            return
                implode(
                    ',',
                    $values
                );
        }

        if ($value === null) {
            return '';
        }

        return
            (string) $value;
    }

    /**
     * Redirect after CREATE / UPDATE / DELETE.
     *
     * Struct fields are expanded using $field.
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

        /*
         * Expand Struct field placeholders.
         */
        $target =
            preg_replace_callback(
                '/\$([a-zA-Z_][a-zA-Z0-9_-]*)/',
                function ($match) use ($data) {

                    $field =
                        $match[1];

                    if (
                        !array_key_exists(
                            $field,
                            $data
                        )
                    ) {
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

                    return (string)$value;
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
     *
     * IMPORTANT:
     *
     * The target is NOT passed as a complete page ID to wl().
     *
     * Instead:
     *
     *   :intern:plm:partmgr?ipn=PRD
     *
     * is split into:
     *
     *   page   = :intern:plm:partmgr
     *   params = ['ipn' => 'PRD']
     *
     * This prevents the '?' from being encoded as
     * part of the page ID.
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

        /*
         * Separate page ID from query string.
         */
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

        /*
         * Clean ONLY the DokuWiki page ID.
         */
        $page =
            cleanID(
                $page
            );

        if ($page === '') {

            /*
             * Fallback target may theoretically contain
             * a query string as well, so split it again.
             */
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

        /*
         * Parse query parameters.
         */
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
         * Let DokuWiki create the canonical URL.
         *
         * The page ID and URL parameters are passed
         * separately.
         */
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