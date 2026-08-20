<?php

class PlmForm
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    /** @var PlmParser */
    private $parser;

    public function __construct(
        Doku_Renderer $renderer,
        PlmStruct $struct,
        PlmParser $parser
    ) {
        $this->renderer = $renderer;
        $this->struct = $struct;
        $this->parser = $parser;
    }

    /**
     * Render the PLM form.
     */
    public function render(
        string $schema,
        string $filter,
        string $content
    ): void {
        if (empty($schema)) {
            $this->error(
                'PLM form: parameter "schema" is required'
            );
            return;
        }

        $params =
            $this->parser->parse(
                $content
            );

        $filter =
            $this->expandFilter(
                $filter
            );

        $fields =
            $this->getFields(
                $params
            );

        $actions =
            $this->getActions(
                $params
            );

        $this->renderForm(
            $schema,
            $filter,
            $fields,
            $actions,
            $params
        );
    }

    /**
     * Expand URI parameters in the filter.
     */
    private function expandFilter(
        string $filter
    ): ?string {
        if ($filter === '') {
            return null;
        }

        $hasEmptyParameter = false;

        $filter =
            preg_replace_callback(
                '/&([a-zA-Z0-9_-]+)/',
                function ($match)
                    use (&$hasEmptyParameter) {

                    $value =
                        $this->getUriParam(
                            $match[1]
                        );

                    if ($value === '') {
                        $hasEmptyParameter = true;
                    }

                    return $value;
                },
                $filter
            );

        if ($hasEmptyParameter) {
            return null;
        }

        return $filter;
    }

    /**
     * Parse field definitions.
     *
     * Example:
     *
     * field: ipn
     * field: description readonly
     */
    private function getFields(
        array $params
    ): array {
        $fields = [];

        foreach (
            $params['field'] ?? []
            as $definition
        ) {

            if (!is_array($definition)) {
                continue;
            }

            if (empty($definition[0])) {
                continue;
            }

            $name =
                trim(
                    $definition[0]
                );

            $readonly =
                isset($definition[1]) &&
                strtolower(
                    trim($definition[1])
                ) === 'readonly';

            $fields[$name] = [
                'readonly' => $readonly,
            ];
        }

        return $fields;
    }

    /**
     * Parse action definitions.
     *
     * Supported actions:
     *
     * create
     * update
     * delete
     * redirect
     *
     * Examples:
     *
     * action: create "Create"
     * action: update "Save"
     * action: delete "Delete"
     * action: redirect "Open" :intern:plm:partview
     * action: redirect "Open" :intern:plm:partview?ipn=$ipn
     */
    private function getActions(
        array $params
    ): array {
        $actions = [];

        foreach (
            $params['action'] ?? []
            as $definition
        ) {

            if (!is_array($definition)) {
                continue;
            }

            if (empty($definition[0])) {
                continue;
            }

            $action =
                strtolower(
                    trim($definition[0])
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
                throw new \InvalidArgumentException(
                    'Unknown PLM action: ' .
                    $action
                );
            }

            $label =
                $definition[1]
                ?? ucfirst($action);

            $redirect =
                $definition[2]
                ?? '';

            $actions[] = [
                'action' =>
                    $action,

                'label' =>
                    $label,

                'redirect' =>
                    $redirect,
            ];
        }

        return $actions;
    }

    /**
     * Render the actual form.
     */
    private function renderForm(
        string $schema,
        ?string $filter,
        array $fields,
        array $actions,
        array $params
    ): void {
        try {

            /*
             * Existing record.
             */
            if ($filter !== null) {

                $record =
                    $this->struct->findOne(
                        $schema,
                        $filter
                    );

                if ($record === null) {

                    $this->error(
                        'PLM form: no Struct record found.'
                    );

                    return;
                }

                $access =
                    $this->struct->getAccessForRecord(
                        $schema,
                        $record['pid'],
                        $record['rid']
                    );

            } else {

                /*
                 * New global Struct record.
                 */
                $access =
                    $this->struct->newGlobalAccess(
                        $schema
                    );
            }

            $data =
                $this->struct->getData(
                    $access
                );

            $this->renderHtml(
                $schema,
                $filter,
                $access,
                $data,
                $fields,
                $actions,
                $params
            );

        } catch (Throwable $e) {

            $this->error(
                'PLM form: ' .
                $e->getMessage()
            );
        }
    }

    /**
     * Render form HTML.
     */
    private function renderHtml(
        string $schema,
        ?string $filter,
        $access,
        array $data,
        array $fields,
        array $actions,
        array $params
    ): void {
        global $ID;

        $formAction =
            wl(
                $ID,
                [],
                true
            );

        $html =
            '<form class="plm_form" method="post" action="' .
            hsc($formAction) .
            '">';

        /*
         * Tell the PLM action plugin this is a form
         * submission.
         */
        $html .=
            '<input type="hidden" ' .
            'name="plm_form_submit" value="1">';

        /*
         * Schema.
         */
        $html .=
            '<input type="hidden" ' .
            'name="plm_schema" value="' .
            hsc($schema) .
            '">';

        /*
         * Filter.
         */
        if ($filter !== null) {

            $html .=
                '<input type="hidden" ' .
                'name="plm_filter" value="' .
                hsc($filter) .
                '">';
        }

        /*
         * Security token.
         */
        ob_start();

        formSecurityToken();

        $html .=
            ob_get_clean();

        /*
         * Explicit fields.
         */
        $hasExplicitFields =
            !empty($fields);

        /*
         * Any action exists.
         *
         * Without an action the complete form is
         * readonly.
         */
        $hasActions =
            !empty($actions);

        /*
         * Struct fields.
         */
        foreach (
            $access->getSchema()->getColumns(false)
            as $column
        ) {

            $name =
                $column->getLabel();

            /*
             * If field definitions exist, only
             * explicitly mentioned fields are shown.
             *
             * Otherwise all fields are shown.
             */
            if (
                $hasExplicitFields &&
                !isset($fields[$name])
            ) {
                continue;
            }

            if (!isset($data[$name])) {
                continue;
            }

            $value =
                $data[$name];

            $editor =
                $value->getValueEditor(
                    'plm_form[' . $name . ']',
                    'plm_' . $name
                );

            $label =
                $column->getTranslatedLabel();

            /*
             * A field is readonly if:
             *
             * 1. no action exists
             * 2. explicitly declared readonly
             */
            $readonly =
                !$hasActions;

            if (
                $hasExplicitFields &&
                isset($fields[$name]) &&
                $fields[$name]['readonly']
            ) {
                $readonly = true;
            }

            if ($readonly) {

                $editor =
                    $this->makeReadonly(
                        $editor
                    );
            }

            $html .=
                '<div class="plm_form_row">' .

                '<label class="plm_form_label">' .
                hsc($label) .
                '</label>' .

                '<div class="plm_form_value">' .
                $editor .
                '</div>' .

                '</div>';
        }

        /*
         * Determine visible actions.
         */
        $visibleActions = [];

        foreach ($actions as $definition) {

            $action =
                $definition['action'];

            /*
             * CREATE only makes sense when there is
             * no existing record.
             */
            if (
                $action === 'create' &&
                $filter !== null
            ) {
                continue;
            }

            /*
             * UPDATE and DELETE require an existing
             * record.
             */
            if (
                in_array(
                    $action,
                    ['update', 'delete'],
                    true
                ) &&
                $filter === null
            ) {
                continue;
            }

            /*
             * REDIRECT is always available.
             *
             * It does not create, update or delete
             * anything.
             */
            if ($action === 'redirect') {
                // always visible
            }

            $visibleActions[] =
                $definition;
        }

        /*
         * Buttons.
         */
        if (!empty($visibleActions)) {

            $html .=
                '<div class="plm_form_buttons">';

            foreach (
                $visibleActions
                as $definition
            ) {

                $html .=
                    '<button type="submit" ' .
                    'name="plm_action" value="' .
                    hsc(
                        $definition['action']
                    ) .
                    '"';

                /*
                 * The redirect belonging to this
                 * action is also stored as a data
                 * attribute for possible client-side
                 * use.
                 */
                if (
                    $definition['redirect'] !== ''
                ) {

                    $html .=
                        ' data-plm-redirect="' .
                        hsc(
                            $definition['redirect']
                        ) .
                        '"';
                }

                $html .=
                    '>' .
                    hsc(
                        $definition['label']
                    ) .
                    '</button>';
            }

            $html .=
                '</div>';
        }

        /*
         * Redirect definitions MUST be inside
         * the form.
         */
        $html .=
            $this->renderRedirectInputs(
                $actions
            );

        $html .=
            '</form>';

        $this->renderer->doc .=
            $html;
    }

    /**
     * Render action-specific redirect definitions.
     */
    private function renderRedirectInputs(
        array $actions
    ): string {
        $html = '';

        foreach ($actions as $definition) {

            $html .=
                '<input type="hidden" ' .
                'name="plm_redirects[' .
                hsc(
                    $definition['action']
                ) .
                ']" value="' .
                hsc(
                    $definition['redirect']
                ) .
                '">';
        }

        return $html;
    }

    /**
     * Make a Struct editor readonly.
     */
    private function makeReadonly(
        string $html
    ): string {
        $html =
            preg_replace(
                '/<input\b/i',
                '<input readonly="readonly"',
                $html
            );

        $html =
            preg_replace(
                '/<textarea\b/i',
                '<textarea readonly="readonly"',
                $html
            );

        $html =
            preg_replace(
                '/<select\b/i',
                '<select disabled="disabled"',
                $html
            );

        return
            '<span class="plm_form_readonly">' .
            $html .
            '</span>';
    }

    /**
     * Get URI parameter.
     */
    private function getUriParam(
        string $name
    ): string {
        global $INPUT;

        return
            $INPUT->str($name) ?? '';
    }

    /**
     * Display an error.
     */
    private function error(
        string $message
    ): void {
        $this->renderer->doc .=
            '<div class="error">' .
            hsc($message) .
            '</div>';
    }
}