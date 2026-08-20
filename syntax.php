<?php

require_once __DIR__ . '/classes/PlmParser.php';
require_once __DIR__ . '/classes/PlmStruct.php';
require_once __DIR__ . '/classes/PlmState.php';
require_once __DIR__ . '/classes/PlmTable.php';
require_once __DIR__ . '/classes/PlmForm.php';
require_once __DIR__ . '/classes/PlmSelect.php';

class syntax_plugin_plm extends DokuWiki_Syntax_Plugin
{
    private const entryPattern =
        '\/plm:(?=[a-zA-Z0-9_-]+)';

    private const exitPattern =
        '\/plm';

    public function getType()
    {
        return 'container';
    }

    public function getPType()
    {
        return 'block';
    }

    public function getSort()
    {
        return 155;
    }

    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern(
            self::entryPattern,
            $mode,
            'plugin_plm'
        );
    }

    public function postConnect()
    {
        $this->Lexer->addExitPattern(
            self::exitPattern,
            'plugin_plm'
        );
    }

    public function handle(
        $match,
        $state,
        $pos,
        Doku_Handler $handler
    ) {
        switch ($state) {

            case DOKU_LEXER_ENTER:

                return [
                    'enter' => true,
                ];

            case DOKU_LEXER_UNMATCHED:

                return [
                    'content' => $match,
                ];

            case DOKU_LEXER_EXIT:

                return [
                    'exit' => true,
                ];
        }

        return null;
    }

    public function render(
        $mode,
        Doku_Renderer $renderer,
        $data
    ) {
        if ($mode !== 'xhtml') {
            return false;
        }

        static $blocks = [];
        static $states = [];

        $rendererId =
            spl_object_id($renderer);

        if (!isset($states[$rendererId])) {

            $states[$rendererId] =
                new PlmState();
        }

        if (!isset($blocks[$rendererId])) {

            $blocks[$rendererId] = [
                'header' => '',
                'content' => '',
            ];
        }

        if (isset($data['enter'])) {

            $blocks[$rendererId] = [
                'header' => '',
                'content' => '',
            ];

            return true;
        }

        if (isset($data['exit'])) {

            $block =
                $blocks[$rendererId];

            $state =
                $states[$rendererId];

            unset(
                $blocks[$rendererId]
            );

            return $this->renderBlock(
                $renderer,
                $block['header'],
                $block['content'],
                $state
            );
        }

        if (isset($data['content'])) {

            if (
                $blocks[$rendererId]['header'] === ''
            ) {

                $content =
                    $data['content'];

                $content =
                    str_replace(
                        ["\r\n", "\r"],
                        "\n",
                        $content
                    );

                $pos =
                    strpos(
                        $content,
                        "\n"
                    );

                if ($pos === false) {

                    $blocks[$rendererId]['header'] =
                        trim(
                            $content
                        );

                } else {

                    $blocks[$rendererId]['header'] =
                        trim(
                            substr(
                                $content,
                                0,
                                $pos
                            )
                        );

                    $blocks[$rendererId]['content'] =
                        substr(
                            $content,
                            $pos + 1
                        );
                }

            } else {

                $blocks[$rendererId]['content'] .=
                    $data['content'];
            }

            return true;
        }

        return false;
    }

    private function renderBlock(
        Doku_Renderer $renderer,
        string $header,
        string $content,
        PlmState $state
    ): bool {

        try {

            $definition =
                $this->parseHeader(
                    $header
                );

            if ($definition === null) {

                $this->error(
                    $renderer,
                    'Invalid PLM header: ' .
                    $header
                );

                return true;
            }

            $type =
                $definition['type'];

            $name =
                $definition['name'];

            $schema =
                $definition['schema'];

            /*
             * Resolve URI parameters before handing
             * the filter to any PLM component.
             *
             * Example:
             *
             *     _pk=&_pk
             *
             * becomes:
             *
             *     _pk=123
             */
            $filter =
                $this->expandUriParameters(
                    $definition['filter']
                );

            $errortext =
                $definition['errortext'];

            switch ($type) {

                case 'table':

                    $struct =
                        new PlmStruct();

                    $parser =
                        new PlmParser();

                    $table =
                        new PlmTable(
                            $renderer,
                            $struct,
                            $parser,
                            $state
                        );

                    $table->render(
                        $name,
                        $schema,
                        $filter,
                        $content,
                        $errortext
                    );

                    return true;

                case 'form':

                    $struct =
                        new PlmStruct();

                    $parser =
                        new PlmParser();

                    $form =
                        new PlmForm(
                            $renderer,
                            $struct,
                            $parser,
                            $state
                        );

                    $form->render(
                        $name,
                        $schema,
                        $filter,
                        $content,
                        $errortext
                    );

                    return true;

                case 'select':

                    $struct =
                        new PlmStruct();

                    $select =
                        new PlmSelect(
                            $renderer,
                            $struct,
                            $state
                        );

                    $select->render(
                        $name,
                        $schema,
                        $filter,
                        $content,
                        $errortext
                    );

                    return true;

                default:

                    $this->error(
                        $renderer,
                        'Unknown PLM component: ' .
                        $type
                    );

                    return true;
            }

        } catch (Throwable $e) {

            $this->error(
                $renderer,
                'PLM: ' .
                $e->getMessage()
            );

            return true;
        }
    }

    /**
     * Resolve references to current URI parameters.
     *
     * Example:
     *
     *     _pk=&_pk
     *
     * with:
     *
     *     ?_pk=123
     *
     * becomes:
     *
     *     _pk=123
     */
    private function expandUriParameters(
        string $filter
    ): string {

        if ($filter === '') {
            return '';
        }

        global $INPUT;

        return
            preg_replace_callback(
                '/&([a-zA-Z0-9_-]+)/',
                function ($match) use ($INPUT) {

                    $parameter =
                        $match[1];

                    if (
                        !isset($INPUT) ||
                        $INPUT === null ||
                        !isset($INPUT->get)
                    ) {
                        return '';
                    }

                    $value =
                        $INPUT->get->str(
                            $parameter
                        );

                    if (
                        $value === null
                    ) {
                        return '';
                    }

                    /*
                     * Escape characters which have
                     * special meaning in Struct filters.
                     */
                    return
                        str_replace(
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
                            (string) $value
                        );
                },
                $filter
            );
    }

    private function parseHeader(
        string $header
    ): ?array {

        $header =
            trim(
                $header
            );

        if (!preg_match(
            '/^'
            . '([a-zA-Z0-9_-]+)'
            . '\s*>\s*'
            . '([a-zA-Z0-9_-]+)'
            . '\s*\|\s*'
            . '([a-zA-Z0-9_-]+)'
            . '(?:\s*\[([^\]]*)\])?'
            . '(?:\s+"((?:\\\\.|[^"\\\\])*)")?'
            . '\s*$'
            . '/',
            $header,
            $match
        )) {
            return null;
        }

        $filter =
            isset($match[4])
                ? trim($match[4])
                : '';

        if (
            isset($match[5])
        ) {

            $errortext =
                stripcslashes(
                    $match[5]
                );

        } else {

            $errortext =
                'not found!';
        }

        return [
            'type' =>
                strtolower(
                    $match[1]
                ),

            'name' =>
                trim(
                    $match[2]
                ),

            'schema' =>
                trim(
                    $match[3]
                ),

            'filter' =>
                $filter,

            'errortext' =>
                $errortext,
        ];
    }

    private function error(
        Doku_Renderer $renderer,
        string $message
    ): void {

        $renderer->doc .=
            '<div class="error">' .
            hsc($message) .
            '</div>';
    }
}