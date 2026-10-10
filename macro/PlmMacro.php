<?php

abstract class PlmMacro {

    public function __construct() {
    }

    /**
     * Write some error message.
     */
    protected function error(Writer $out, string $msg) : void {
        $html = new HtmlBuilder();
        $html->tag('div', $msg, 'error');
        $html->flush($out);
    }

    /**
     * Initialize the from given config.
     * @throws Exception
     */
    public abstract function init(mixed $config): void;

    /**
     * Override function for rendering the content.
     */
    public abstract function render(Writer $out): void;
    
}
