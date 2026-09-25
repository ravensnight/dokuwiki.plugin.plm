<?php

class HtmlContext {

    private readonly string $tag;
    private readonly ?string $css;

    /** @var array<string, mixed> */
    private readonly ?array $params;

    /** @var string[] */
    private array $lines = [];

    /**
     * @param string $tag
     * @param array<string, mixed> $params
     */
    public function __construct(string $tag, ?string $css_class = null, ?array $params = null)
    {
        $this->tag = $tag;
        $this->css = $css_class;
        $this->params = $params;
    }

    public function add(string $line) : self {
        if ($line !=='' ) {
            $this->lines[] = $line;
        }

        return $this;
    }

    public function build() : string {
        $result = '<' . $this->tag;

        if ($this->css !== null && $this->css !== '') {
            $result .= ' class="' . $this->css . '"';
        }

        if ($this->params !== null) {
            foreach ($this->params as $key => $value) {
                $result .= ' ' . $key . '="' . htmlspecialchars(
                    (string) $value,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) . '"';
            }
        }

        $result .= '>';


        foreach ($this->lines as $line) {
            $result .= $line;
        }

        $result .= '</' . $this->tag . '>';
        return $result;
    }
}
