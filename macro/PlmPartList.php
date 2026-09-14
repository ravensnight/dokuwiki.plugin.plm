<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmPartList extends PlmMacro
{
    private string $variantName;

    public function __construct(Doku_Renderer $renderer, PlmDB $db, string $variantName)
    {
        parent::__construct($renderer, $db);

        $this->variantName = trim($variantName);
    }

    public function render(MacroHeader $header, ?string $content = null): void
    {
        $this->out()->opn('div', 'plm-partlist');

        $parts = Part::entries($this->db);
        if ($parts === []) {
            $this->out()
                ->tag('div', 'No parts found.', 'plm-partlist-empty')
                ->flush();
            return;
        }

        $this->out()->opn('table', 'plm-partlist-table');
        $this->renderHeader();

        $this->out()->opn('tbody');
        foreach ($parts as $part) {
            $this->renderPart($part);
        }
        $this->out()->cls();

        $this->out()->cls()->flush();
    }

    private function renderHeader(): void
    {
        $this->out()->opn('thead')->opn('tr');
        $this->out()->tag('th', 'ID');
        $this->out()->tag('th', 'IPN');
        $this->out()->tag('th', 'Description');
        $this->out()->tag('th', 'Category ID');
        $this->out()->cls()->cls();
    }

    private function renderPart(Part $part): void
    {
        $partPage = $this->partPageId();
        $partUrl = wl($partPage, ['pk' => (string) $part->pk]);

        $this->out()->opn('tr');
        $this->out()->tag('td', hsc((string) $part->pk));
        $this->out()
            ->opn('td')
            ->tag('a', hsc($part->ipn), null, ['href' => $partUrl])
            ->cls();
        $this->out()->tag('td', hsc($part->description ?? ''));
        $this->out()->tag('td', hsc((string) $part->categoryId));
        $this->out()->cls();
    }

    private function partPageId(): string
    {
        global $ID;

        $namespace = getNS($ID);
        return $namespace === '' ? 'part' : $namespace . ':part';
    }
}
