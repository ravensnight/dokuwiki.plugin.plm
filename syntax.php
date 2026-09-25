<?php

require_once __DIR__ . '/persist/DbObject.php';
require_once __DIR__ . '/persist/DbEnum.php';
require_once __DIR__ . '/persist/Category.php';
require_once __DIR__ . '/persist/Status.php';
require_once __DIR__ . '/persist/PartVersion.php';
require_once __DIR__ . '/persist/PartItemRef.php';
require_once __DIR__ . '/persist/Part.php';
require_once __DIR__ . '/persist/Product.php';
require_once __DIR__ . '/persist/ProductVariant.php';
require_once __DIR__ . '/persist/VariantItemRef.php';
require_once __DIR__ . '/persist/PlmDB.php';

require_once __DIR__ . '/util/HtmlContext.php';
require_once __DIR__ . '/util/HtmlBuilder.php';
require_once __DIR__ . '/util/Writer.php';
require_once __DIR__ . '/util/RenderWriter.php';
require_once __DIR__ . '/util/ResponseWriter.php';

require_once __DIR__ . '/macro/MacroHeader.php';
require_once __DIR__ . '/macro/RenderContext.php';
require_once __DIR__ . '/macro/HeaderParser.php';
require_once __DIR__ . '/macro/PlmParser.php';
require_once __DIR__ . '/macro/PlmState.php';
require_once __DIR__ . '/macro/PlmMacro.php';
require_once __DIR__ . '/macro/PlmBom.php';
require_once __DIR__ . '/macro/PlmAjax.php';


class syntax_plugin_plm extends DokuWiki_Syntax_Plugin
{
    private const entryPattern = '\/plm:(?=[a-zA-Z0-9_-]+)';
    private const exitPattern = '\/plm';


    /** @var PlmDB $plmdb */
    private $plmdb;

    public function getType() {
        return 'container';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 155;
    }

    public function connectTo($mode) {
        $this->Lexer->addEntryPattern( self::entryPattern, $mode, 'plugin_plm' );
    }

    public function postConnect() {
        $this->Lexer->addExitPattern( self::exitPattern, 'plugin_plm');
    }

    private function persist() : PlmDB {
        if ($this->plmdb !== null) {
            return $this->plmdb;
        }

        $this->plmdb = new PlmDB();
        return $this->plmdb;
    }

    public function handle( $match, $state, $pos, Doku_Handler $handler) {
        switch ($state) {

            case DOKU_LEXER_ENTER:                
                return [ 'enter' => true ];

            case DOKU_LEXER_UNMATCHED:
                return [ 'content' => $match ];

            case DOKU_LEXER_EXIT:
                return [ 'exit' => true];

            default:
                echo "State: " . $state;
                return null;
        }
    }

    public function render( $mode, Doku_Renderer $renderer, $data) {

        if ($mode !== 'xhtml') {
            return false;
        }

        static $renderContext = [];
        $rendererId = spl_object_id($renderer);

        if (!isset($renderContext[$rendererId])) {
            $renderContext[$rendererId] = RenderContext::createEmpty(new PlmState());
        }

        if (isset($data['enter'])) {
            $renderContext[$rendererId]->clear();
            return true;
        }
        elseif (isset($data['exit'])) {
            /** @var RenderContext */
            $context = $renderContext[$rendererId];
            unset($renderContext[$rendererId]);

            return $this->renderBlock($renderer, $context);
        }
        elseif (isset($data['content'])) {
            $renderContext[$rendererId]->fromMacroContent($data['content']);
            return true;
        }

        return false;

    }

    private function renderBlock( Doku_Renderer $renderer, RenderContext $renderContext): bool {
        try {

            /*
             * Everything else uses the normal PLM header syntax.
             */
            $header = HeaderParser::parse($renderContext->header);
            if ($header === null) {
                $this->error( $renderer, 'Invalid PLM header: ' . $renderContext->header);
                return true;
            }
            
            $writer = new RenderWriter($renderer);
            $writer->enableCache(false);

            switch ($header->macro) {

                case 'bom':

                    // /plm:bom > MC1210F-BLACK                    
                    $bom = new PlmBom( $this->persist(), $header->context );
                    $bom->render( $writer, $header, $renderContext->body );

                    return true;

                case 'ajax':

                    $ajax = new PlmAjax($header->context);
                    $ajax->render($writer, $header, $renderContext->body );
                    return true;

                default:

                    $this->error( $renderer, 'Unknown PLM component: ' . $header->macro);
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
