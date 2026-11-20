<?php

/**
 * PLM Manager Macro
 * Provides a Single Page Application (SPA) interface for managing all PLM entities.
 *
 * Usage: {{plm:manager}}
 */

class PlmBom2 extends PlmMacro {
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
        $file = PLM_HTML_DIR . '/bommain.html';

        $content = file_get_contents($file);
        if ($content === null) {
            throw new Exception("Failed to load page content from file: " . $file);
        }

        // inject the context
        $context = json_encode($this->config);
        $content = str_replace('${context}', $context, $content);

        $out->write($content);
    }
}
