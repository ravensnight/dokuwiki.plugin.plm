<?php
// Load all plugin components
require_once(__DIR__ . '/init.php');

use dokuwiki\Parsing\Handler;

class syntax_plugin_plm extends DokuWiki_Syntax_Plugin
{
    private const entryPattern = '<!plm:';
    private const exitPattern = '!>';

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

    public function handle( $match, $state, $pos, Handler $handler) {

        switch ($state) {

            case DOKU_LEXER_ENTER:                
                return [ 'enter' => true ];

            case DOKU_LEXER_UNMATCHED:
                return [ 'content' => $match ];                

            case DOKU_LEXER_EXIT:
                return [ 'exit' => true ];

            default:
                return null;
        }
    }

    public function render( $mode, Doku_Renderer $renderer, $data) {

        if ($mode !== 'xhtml') {
            return false;
        }

        /** @var RenderContext[] $renderContext */
        static $renderContext = [];
        $rendererId = spl_object_id($renderer);

        if (!isset($renderContext[$rendererId])) {
            // create the rendercontext, if not exising yet.
            $renderContext[$rendererId] = RenderContext::create();
        }
        
        if (isset($data['enter'])) {
            // clear the context on enter
            $renderContext[$rendererId]->clear();
            return true;
        }
        elseif (isset($data['exit'])) {
            // remove the render context on exit and send to 
            // rendering function

            /** @var RenderContext $context */
            $context = $renderContext[$rendererId];
            unset($renderContext[$rendererId]);

            return $this->renderBlock($renderer, $context);
        }
        elseif (isset($data['content'])) {

            /** @var string $content */
            $content = $data['content'];

            $renderContext[$rendererId]->parse($content);
            return true;
        }

        return false;

    }

    private function renderBlock(Doku_Renderer $renderer, RenderContext $context): bool
    {

        $writer = new RenderWriter($renderer);
        $writer->enableCache(false);

        try {

            $macro = null;

            switch ($context->type) {

                case 'bom':
                    // plm:bom > MC1210F-BLACK                    
                    $macro = new PlmBomMacro(PlmDB::get());
                    break;

                case 'manager':
                    // Entry point for the Single Page Application
                    $macro = new PlmManager();
                    break;

                default:
                    $this->error($writer, 'Unknown PLM component: ' . $context->type);
                    break;
            }

            if ($macro === null) {
                $this->error($writer, "PLM Internal Error: Macro instance not created.");
            } else {
                try {
                    $macro->init($context->config ?? []);
                    $macro->render($writer);
                } catch (Exception $e) {
                    $this->error($writer, "PLM Macro Initialization failed. " . $e->getMessage());
                }
            }

            return true;
        } catch (Throwable $e) {

            $this->error($writer, 'PLM Error: ' . $e->getMessage() . ": " . json_encode($e->getTrace(), JSON_PRETTY_PRINT));
            return true;
        }
    }

    private function error(Writer $out, string $message ): void {
        $html = new HtmlBuilder();
        $html->tag('div', $message, 'error');
        $html->flush($out);
    }
}

