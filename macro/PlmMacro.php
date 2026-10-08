<?php

abstract class PlmMacro {

    public function __construct() {
    }

    /**
     * Write some error message.
     */
    protected function error(HtmlBuilder $out, string $msg) : void {
        $out->tag('div', $msg, 'error');
    }

    /**
     * Initialize the from given config.
     * @throws Exception
     */
    public abstract function init(mixed $config): void;

    /**
     * Override function for rendering the content.
     */
    public abstract function render(HtmlBuilder $html): void;
}
