<?php

abstract class PlmBakeryMacro extends PlmMacro {

    /** @var array */
    private $config = [];

    public function __construct() {
    }

    /**
     * Initialize the from given config.
     * @throws Exception
     */
    public final function init(mixed $config) : void {
        $this->config = is_array($config) ? $config : [];
    }

    /** Get the template to render */
    protected abstract function getTemplateName() : string;

    /** Get the context to use for rendering */
    protected abstract function getContext(array $config): array;

    /**
     * @throws Exception
     */
    public final function render(Writer $out): void
    {
        try {
            $bakery = new HtmlBakery();
            $templatePath = PLM_PLUGIN_TEMPLATE_DIR . "/" . $this->getTemplateName();
            $context = $this->getContext($this->config);
            $bakery->parse( $out, $templatePath, $context );
        } catch (Exception $e) {
            $this->error($out, "PlmTest Rendering Error: " . $e->getMessage() . ": " . $e->getTraceAsString());
        }
    }
}
