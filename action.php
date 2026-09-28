<?php

if (!defined('DOKU_INC')) {
    die();
}

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


require_once __DIR__ . '/api/Event.php';
require_once __DIR__ . '/api/ApiBase.php';
require_once __DIR__ . '/api/ApiPart.php';
require_once __DIR__ . '/api/ApiVersion.php';

require_once __DIR__ . '/service/HtmlBaseFactory.php';
require_once __DIR__ . '/service/PartFormFactory.php';
require_once __DIR__ . '/service/PartListFactory.php';

class action_plugin_plm extends DokuWiki_Action_Plugin
{
    /**
     * @var PlmDB
     */
    private ?PlmDB $plmdb = null;

    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook(
            'DOKUWIKI_STARTED', 'BEFORE', $this, 'handleAjax'
        );
    }

    public function handleAjax( Doku_Event $event, $param ): void {

        global $INPUT;
        $route = $INPUT->get->str('plmapi');

        if (!str_starts_with($route, 'v1/')) {
            return;
        }

        // DokuWiki soll die Anfrage nicht weiter verarbeiten
        $event->preventDefault();
        $event->stopPropagation();

        try {
            ApiBase::handleRequest(PlmDB::get(), $route);
        } catch (Throwable $e) {
            error_log( 'PLM API error: ' . $e->getMessage() );
            $this->sendError( 500, 'Internal server error: ' . $e->getMessage());
        }

        exit;
    }

    private function sendError(
        int $status,
        string $message
    ): void {

        http_response_code($status);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode(
            [
                'success' => false,
                'error'   => $message
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
