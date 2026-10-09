<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmPartEdit extends PlmMacro
{
    private string $ctx = '';

    public function init(mixed $config): void
    {
        if (!isset($config['id'])) {
            throw new Exception('plm:partedit - missing attribute id.');
        }

        $this->ctx = trim($config['id']);
    }

    protected function build(HtmlBuilder $html): void
    {
        $db = PlmDB::get();
        $html->opn('div', 'plm_partedit', [ 'id' => $this->ctx, ]);
        PartFormFactory::renderCreateForm($html, $db);
        $html->cls();
    }
}
