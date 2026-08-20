<?php

class PlmForm
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    /** @var PlmParser */
    private $parser;

    /** @var PlmState */
    private $state;

    /**
     * True when a PLM state reference used by the
     * filter exists but contains no value.
     */
    private bool $filterStateMissing = false;

    /**
     * CSS class for a normal "nothing found" message.
     */
    private const EMPTY_CLASS = 'plm_empty';

    public function __construct(
        Doku_Renderer $renderer,
        PlmStruct $struct,
        PlmParser $parser,
        PlmState $state
    ) {
        $this->renderer = $renderer;
        $this->struct = $struct;
        $this->parser = $parser;
        $this->state = $state;
    }

    /**
     * Render the PLM form.
     */
    public function render(
        string $name,
        string $schema,
        string $filter,
        string $content,
        string $errortext = 'not found!'
    ): void {

        if ($schema === '') {
            $this->error(
                'PLM form: parameter "schema" is required'
            );
            return;
        }

        $params =
            $this->parser->parse(
                $content
            );

        /*
         * Expand references to PLM state.
         *
         * Example:
         *
         *     name=$tablecompanies.name
         *
         * becomes:
         *
         *     name=Any
         */
        $filter =
            $this->expandFilter(
                $filter
            );

        /*
         * A missing state value means that the requested
         * record cannot be found.
         *
         * This is different from a completely empty filter,
         * which means that a new global record should be edited.
         */
        if ($this->filterStateMissing) {

            $this->renderEmpty(
                $errortext
            );

            return;
        }

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
            $params,
            $errortext
        );
    }

    /**
     * Expand references to values stored in PlmState.
     */
    private function expandFilter(
        string $filter
    ): ?string {

        /*
         * Reset the state for every render.
         */
        $this->filterStateMissing = false;

        if ($filter === '') {
            return null;
        }

        $filter =
            preg_replace_callback(
                '/\$([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_.-]+)/',
                function ($match) {

                    $table =
                        $match[1];

                    $field =
                        $match[2];

                    $value =
                        $this->state->getFilterValue(
                            $table,
                            $field
                        );

                    if ($value === '') {

                        $this->filterStateMissing =
                            true;
                    }

                    return $value;
                },
                $filter
            );

        if ($this->filterStateMissing) {
            return null;
        }

        return $filter;
    }

    /**
     * Parse field definitions.
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
        array $params,
        string $errortext
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

                    /*
                     * No matching record is a normal
                     * application state, not a technical
                     * error.
                     */
                    $this->renderEmpty(
                        $errortext
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

            /*
             * Technical errors remain real errors.
             */
            $this->error(
                'PLM form: ' .
                $e->getMessage()
            );
        }
    }

    /**
     * Render the normal "nothing found" message.
     *
     * The text is parsed as DokuWiki content and
     * therefore supports normal DokuWiki markup.
     */
    private function renderEmpty(
        string $text
    ): void {

        if (trim($text) === '') {
            return;
        }

        $instructions =
            p_get_instructions(
                $text
            );

        $info = [];

        $html =
            p_render(
                'xhtml',
                $instructions,
                $info
            );

        $this->renderer->doc .=
            '<div class="' .
            self::EMPTY_CLASS .
            '">' .
            $html .
            '</div>';
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
         * Form submission marker.
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
         * Expanded filter.
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

        $hasExplicitFields =
            !empty($fields);

        $hasActions =
            !empty($actions);

        /*
         * Struct fields.
         */
        foreach (
            $access->getSchema()->getColumns(false)
            as $column
        ) {

            $fieldName =
                $column->getLabel();

            if (
                $hasExplicitFields &&
                !isset($fields[$fieldName])
            ) {
                continue;
            }

            if (!isset($data[$fieldName])) {
                continue;
            }

            $value =
                $data[$fieldName];

            $editor =
                $value->getValueEditor(
                    'plm_form[' . $fieldName . ']',
                    'plm_' . $fieldName
                );

            $label =
                $column->getTranslatedLabel();

            /*
             * No action means readonly.
             */
            $readonly =
                !$hasActions;

            if (
                $hasExplicitFields &&
                isset($fields[$fieldName]) &&
                $fields[$fieldName]['readonly']
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
         * Visible actions.
         */
        $visibleActions = [];

        foreach ($actions as $definition) {

            $action =
                $definition['action'];

            if (
                $action === 'create' &&
                $filter !== null
            ) {
                continue;
            }

            if (
                in_array(
                    $action,
                    [
                        'update',
                        'delete'
                    ],
                    true
                ) &&
                $filter === null
            ) {
                continue;
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
         * Redirect definitions.
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
     * Display a technical error.
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
