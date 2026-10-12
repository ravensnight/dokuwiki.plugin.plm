<?php

if (!defined('DOKU_INC')) {
    die();
}

// Load all plugin components
require_once(__DIR__ . '/init.php');

use \dokuwiki\Extension\EventHandler;
use \dokuwiki\Extension\ActionPlugin;

class action_plugin_plm extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook(
            'DOKUWIKI_STARTED', 'BEFORE', $this, 'handleAjax'
        );
    }

    /**
     * Ajax handler being called by DokuWiki due to registration.
     */
    public function handleAjax( Doku_Event $event, $param ): void {

        global $INPUT;
        $route = $INPUT->get->str('plm');

        if (!str_starts_with($route, '/api/v1/')) {
            return;
        }

        // DokuWiki soll die Anfrage nicht weiter verarbeiten
        $event->preventDefault();
        $event->stopPropagation();

        try {            
        
            AbstractApi::handleRequest($route);

        } catch (Throwable $e) {
            error_log( 'PLM API error: ' . $e->getTraceAsString());

            $out = new ResponseWriter();
            AbstractApi::error($out, 500, "PLM API error: " . $e->getMessage());
        }

        exit;
    }

    private function sendError( int $status, string $message ): void {
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

