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

    /** @var PlmReference */
    private $reference;

    /**
     * True when a PLM state/request reference used by the
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

        $this->reference =
            new PlmReference(
                $state,
                $struct
            );
    }

    /**
     * Render the PLM form.
     */
    public function render( MacroHeader $header, string $content ): void {

        if ($header->reference === '') {
            $this->error(
                'PLM form: parameter "schema" is required'
            );

            return;
        }

        $params = $this->parser->parse( $content );

        /*
         * Register templates with the central
         * reference resolver.
         *
         * Parser template definitions:
         *
         *     [
         *         ['details', 'Details', '...'],
         *         ['foo', 'Foo', '...'],
         *     ]
         *
         * become:
         *
         *     [
         *         'details' => '...',
         *         'foo'     => '...'
         *     ]
         */
        $this->reference->setTemplates( $this->getTemplates( $params ) );

        /*
         * Expand references in the filter.
         *
         * Supported:
         *
         *     &_pk
         *     %context.filter.field
         *     %context.current.field
         */
        $originalFilter = $header->filter;

        $filter = $this->expandFilter( $header->filter );

        /*
         * A missing reference value means that the requested
         * record cannot be found.
         */
        if ($this->filterStateMissing) {
            $this->renderEmpty( $header->message );
            return;
        }

        $fields = $this->getFields( $params );
        $actions = $this->getActions( $params );

        $this->renderForm(
            $header->reference,
            $filter,
            $fields,
            $actions,
            $params,
            $header->message
        );
    }

    /**
     * Expand PLM filter references through PlmReference.
     *
     * The filter value is additionally escaped because it
     * will be passed to Struct as a filter expression.
     */
    private function expandFilter(
        ?string $filter
    ): ?string {

        $this->filterStateMissing = false;

        $filter = trim( $filter ?? '');

        if ($filter === '') {
            return null;
        }

        /*
         * Resolve references one by one so that we can
         * distinguish an unresolved reference from a
         * resolved-but-empty value.
         *
         * Request:
         *
         *     &_pk
         *
         * State:
         *
         *     %context.filter.field
         *     %context.current.field
         */
        $filter =
            preg_replace_callback(
                '/&([a-zA-Z0-9_-]+)/',
                function ($match) {

                    $value =
                        $this->reference->resolve(
                            '&' . $match[1]
                        );

                    if ($value === null || $value === '') {

                        $this->filterStateMissing =
                            true;

                        return '';
                    }

                    return $this->escapeFilterValue(
                        $value
                    );
                },
                $filter
            );

        if ($this->filterStateMissing) {
            return null;
        }

        /*
         * State references.
         *
         *     %context.scope.field
         */
        $filter =
            preg_replace_callback(
                '/%([a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+)/',
                function ($match) {

                    $value =
                        $this->reference->resolve(
                            '%' . $match[1]
                        );

                    if ($value === null || $value === '') {

                        $this->filterStateMissing =
                            true;

                        return '';
                    }

                    return $this->escapeFilterValue(
                        $value
                    );
                },
                $filter
            );

        if ($this->filterStateMissing) {
            return null;
        }

        return trim($filter);
    }

    /**
     * Escape a value inserted into a Struct filter.
     */
    private function escapeFilterValue(
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
     * Get all templates configured for this form.
     *
     * Template definitions are parser entries:
     *
     *     [
     *         'details',
     *         'Details',
     *         'intern:plm:details:$category:$part._pk'
     *     ]
     *
     * PlmReference only needs the template name and
     * template text.
     */
    private function getTemplates(
        array $params
    ): array {

        $templates = [];

        foreach (
            $params['template'] ?? []
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
                    (string) $definition[0]
                );

            if ($name === '') {
                continue;
            }

            $text =
                implode(
                    ' ',
                    array_slice(
                        $definition,
                        2
                    )
                );

            $templates[$name] =
                $text;
        }

        return $templates;
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

            $fieldName =
                trim(
                    $definition[0]
                );

            $readonly =
                isset($definition[1]) &&
                strtolower(
                    trim($definition[1])
                ) === 'readonly';

            $fields[$fieldName] = [
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

            $this->error(
                'PLM form: ' .
                $e->getMessage()
            );
        }
    }

    /**
     * Render the normal "nothing found" message.
     */
    private function renderEmpty(
        ?string $text
    ): void {

        if ($text === null) {
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

        $html .=
            '<input type="hidden" ' .
            'name="plm_form_submit" value="1">';

        $html .=
            '<input type="hidden" ' .
            'name="plm_schema" value="' .
            hsc($schema) .
            '">';

        /*
         * Store the already expanded filter.
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
         * Preserve the complete PLM state across
         * the form POST.
         *
         * The state is normally present in the current
         * page URL as ?plm=...
         *
         * It has to be copied into the POST because the
         * form action itself intentionally points to the
         * current page without the PLM state parameter.
         */
        $state =
            $this->state->encode();

        if ($state !== '') {

            $html .=
                '<input type="hidden" ' .
                'name="plm" value="' .
                hsc($state) .
                '">';
        }

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

                    /*
                     * Redirects may themselves contain
                     * PLM references/templates.
                     *
                     * At this point there is no current
                     * Struct row attached to the reference
                     * resolver, so only request/state/template
                     * references can be resolved here.
                     */
                    $redirect =
                        $this->reference->expand(
                            $definition['redirect']
                        );

                    $html .=
                        ' data-plm-redirect="' .
                        hsc($redirect) .
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

            $redirect =
                $this->reference->expand(
                    $definition['redirect']
                );

            $html .=
                '<input type="hidden" ' .
                'name="plm_redirects[' .
                hsc(
                    $definition['action']
                ) .
                ']" value="' .
                hsc(
                    $redirect
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
