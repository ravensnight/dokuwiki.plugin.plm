<?php

require_once __DIR__ . '/../service/ui/HtmlBakery.php';

class PlmTest extends PlmMacro {
    /**
     * @var array
     */
    private $config;

    /**
     * @throws Exception
     */
    public function init(mixed $config): void {
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * @throws Exception
     */    
    public function render(Writer $out): void {
        $bakery = new HtmlBakery();
        // The template-example.xml is located in service/ui/
        $templatePath = __DIR__ . '/tpl/part-create-form.xml';
        
        try {
            $bakery->parse(
                $out, 
                $templatePath, 
                $this->config
            );
        } catch (Exception $e) {
            $this->error($out, "PlmTest Rendering Error: " . $e->getMessage() . ": " . $e->getTraceAsString());
        }
    }
}
