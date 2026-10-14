<?php

/**
 * PLM Manager Macro
 * Provides a Single Page Application (SPA) interface for managing all PLM entities.
 *
 * Usage: {{plm:manager}}
 */

class PlmManager extends PlmMacro {
    private $config = [];

    public function __construct() {
        parent::__construct();
    }

    /**
     * Initialize the macro with configuration.
     * @param mixed $config
     * @throws Exception
     */
    public function init(mixed $config): void {
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * Override function for rendering the content.
     * @param Writer $out
     */
    public function render(Writer $out): void {

        // Load app main page.
        $file = PLM_HTML_DIR . '/appmain.html';

        $content = file_get_contents($file);
        if ($content === null) {
            throw new Exception("Failed to load page content from file: " . $file);
        }

        $out->write($content);
    }
}
