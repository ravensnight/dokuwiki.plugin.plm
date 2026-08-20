<?php

require_once __DIR__ . '/classes/PlmParser.php';
require_once __DIR__ . '/classes/PlmStruct.php';
require_once __DIR__ . '/classes/PlmTable.php';
require_once __DIR__ . '/classes/PlmForm.php';
require_once __DIR__ . '/classes/PlmSelect.php';

class syntax_plugin_plm extends DokuWiki_Syntax_Plugin
{
    /**
     * PLM opening marker.
     *
     * We deliberately let DokuWiki match only "/plm:".
     *
     * The remainder of the first line is parsed ourselves.
     *
     * Examples:
     *
     * /plm:table > plm_part[ipn=&ipn]
     * /plm:form > plm_part
     * /plm:select > plm_part[ipn=&ipn]
     */
    private const entryPattern =
        '\/plm:(?=[a-zA-Z0-9_-]+)';

    /**
     * PLM closing marker.
     */
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

            /*
             * ENTER
             *
             * DokuWiki gives us only:
             *
             *     /plm:
             *
             * The "select > schema[filter]" part
             * arrives as the first unmatched content.
             */
            case DOKU_LEXER_ENTER:

                return [
                    'enter' => true,
                ];

            /*
             * CONTENT
             */
            case DOKU_LEXER_UNMATCHED:

                return [
                    'content' => $match,
                ];

            /*
             * EXIT
             */
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

        $rendererId =
            spl_object_id($renderer);

        if (!isset($blocks[$rendererId])) {

            $blocks[$rendererId] = [
                'header' => '',
                'content' => '',
            ];
        }

        /*
         * ENTER
         */
        if (isset($data['enter'])) {

            $blocks[$rendererId] = [
                'header' => '',
                'content' => '',
            ];

            return true;
        }

        /*
         * EXIT
         */
        if (isset($data['exit'])) {

            $block =
                $blocks[$rendererId];

            unset(
                $blocks[$rendererId]
            );

            return $this->renderBlock(
                $renderer,
                $block['header'],
                $block['content']
            );
        }

        /*
         * CONTENT
         */
        if (isset($data['content'])) {

            /*
             * The first line contains the PLM
             * component declaration.
             *
             * Everything after the first newline
             * is actual PLM content.
             */
            if ($blocks[$rendererId]['header'] === '') {

                $content =
                    $data['content'];

                /*
                 * Normalize line endings.
                 */
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

                    /*
                     * No newline yet.
                     *
                     * Keep the complete chunk as header.
                     */
                    $blocks[$rendererId]['header'] =
                        trim($content);

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

    /**
     * Parse and render a PLM block.
     */
    private function renderBlock(
        Doku_Renderer $renderer,
        string $header,
        string $content
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

            $schema =
                $definition['schema'];

            $filter =
                $definition['filter'];

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
                            $parser
                        );

                    $table->render(
                        $schema,
                        $filter,
                        $content
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
                            $parser
                        );

                    $form->render(
                        $schema,
                        $filter,
                        $content
                    );

                    return true;

                case 'select':

                    $struct =
                        new PlmStruct();

                    $select =
                        new PlmSelect(
                            $renderer,
                            $struct
                        );

                    $select->render(
                        $schema,
                        $filter,
                        $content
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
     * Parse:
     *
     *     select
     *     select > plm_part
     *     select > plm_part[ipn=&ipn]
     */
    private function parseHeader(
        string $header
    ): ?array {

        $header =
            trim($header);

        if (!preg_match(
            '/^([a-zA-Z0-9_-]+)'
            . '(?:\s*>\s*([a-zA-Z0-9_-]+))?'
            . '(?:\s*\[([^\]]*)\])?'
            . '\s*$/',
            $header,
            $match
        )) {
            return null;
        }

        return [
            'type' =>
                strtolower(
                    $match[1]
                ),

            'schema' =>
                isset($match[2])
                    ? trim($match[2])
                    : '',

            'filter' =>
                isset($match[3])
                    ? trim($match[3])
                    : '',
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