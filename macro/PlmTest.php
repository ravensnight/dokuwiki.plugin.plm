<?php

require_once __DIR__ . '/../service/ui/HtmlBakery.php';

class PlmTest extends PlmBakeryMacro {

    #[Override]
    protected function getTemplateName(): string {
        return 'part-form.xml';
    }

    protected function getContext(array $context) : array {
        return $context;
    }
}

