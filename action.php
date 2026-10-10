<?php

if (!defined('DOKU_INC')) {
    die();
}

// Load all plugin components
require_once(__DIR__ . '/init.php');

use \dokuwiki\Extension\EventHandler;

class action_plugin_plm extends DokuWiki_Action_Plugin
{
    /**
     * @var PlmDB
     */
    private ?PlmDB $plmdb = null;

    public function register(EventHandler $controller)
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

