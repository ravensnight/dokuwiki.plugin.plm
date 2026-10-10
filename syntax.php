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

require_once __DIR__ . '/service/ui/HtmlBakery.php';
require_once __DIR__ . '/service/ui/Renderer.php';
require_once __DIR__ . '/service/ui/DefaultRenderer.php';
require_once __DIR__ . '/service/ui/RendererRegistry.php';
require_once __DIR__ . '/service/ui/ProviderRegistry.php';
require_once __DIR__ . '/service/ui/Provider.php';
require_once __DIR__ . '/service/ui/SimpleProvider.php';
require_once __DIR__ . '/service/ui/CategoriesProvider.php';

require_once __DIR__ . '/service/HtmlBaseFactory.php';
require_once __DIR__ . '/service/PartListFactory.php';
require_once __DIR__ . '/service/PartFormFactory.php';

require_once __DIR__ . '/util/HtmlContext.php';
require_once __DIR__ . '/util/HtmlBuilder.php';
require_once __DIR__ . '/util/Writer.php';
require_once __DIR__ . '/util/RenderWriter.php';
require_once __DIR__ . '/util/ResponseWriter.php';
require_once __DIR__ . '/util/SubstitutionUtility.php';

require_once __DIR__ . '/macro/RenderContext.php';
require_once __DIR__ . '/macro/PlmMacro.php';
require_once __DIR__ . '/macro/PlmBuilderMacro.php';
require_once __DIR__ . '/macro/PlmBakeryMacro.php';
require_once __DIR__ . '/macro/PlmTest.php';
require_once __DIR__ . '/macro/PlmBom.php';
require_once __DIR__ . '/macro/PlmParts.php';
require_once __DIR__ . '/macro/PlmPartEdit.php';
require_once __DIR__ . '/macro/PlmVersions.php';

define('PLM_PLUGIN_TEMPLATE_DIR', __DIR__ . '/service/ui/tpl');

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

    private function renderBlock(Doku_Renderer $renderer, RenderContext $context): bool {

        $writer = new RenderWriter($renderer);
        $writer->enableCache(false);

        try {                        

            $macro = null;

            switch ($context->type) {

                case 'bom':
                    // plm:bom > MC1210F-BLACK                    
                    $macro = new PlmBom(PlmDB::get());
                    break;

                case 'parts':
                    $macro = new PlmParts();
                    break;

                case 'partedit':
                    $macro = new PlmPartEdit();
                    break;

                case 'versions':
                    $macro = new PlmVersions();
                    break;

                case 'test':
                    $macro = new PlmTest();
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

            $this->error($writer, 'PLM Error: ' . $e->getMessage() . ": " . json_encode($e->getTrace(), JSON_PRETTY_PRINT) );
            return true;
        }
    }

    private function error(Writer $out, string $message ): void {
        $html = new HtmlBuilder();
        $html->tag('div', $message, 'error');
        $html->flush($out);
    }
}
