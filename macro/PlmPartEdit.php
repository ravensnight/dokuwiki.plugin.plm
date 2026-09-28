<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmPartEdit extends PlmMacro
{
    private string $ctx;

    public function __construct(string $context)
    {
        parent::__construct();
        $this->ctx = trim($context);
    }

    public function render(HtmlBuilder $html, MacroHeader $header, ?string $body = null): void
    {
        $db = PlmDB::get();

        $html->opn('div', 'plm_partedit', [ 'id' => $this->ctx, ]);
        PartFormFactory::renderCreateForm($html, $db);
        $html->cls();
    }
}
