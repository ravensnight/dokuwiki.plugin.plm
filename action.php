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

            $uri = parse_url($route);

            $path = $uri['path'];

            $segments = explode('/', trim($path, '/'));
            $segments = array_slice($segments, 2);

            $params = [];
            parse_str($uri['query'] ?? '', $params);

            AbstractApi::handleRequest($segments, $params);
            exit;

        } catch (Throwable $e) {
            error_log( 'PLM API error: ' . $e->getTraceAsString());

            $out = new ResponseWriter();
            AbstractApi::halt($out, 500, "PLM API error: " . $e->getMessage(), $e->getTrace());
        }
    }
}

