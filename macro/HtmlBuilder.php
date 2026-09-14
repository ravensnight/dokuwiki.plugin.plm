<?php 

class HtmlBuilder {

    private array $stack = [];
    private readonly Doku_Renderer $renderer;

    /**
     * Constructor
     */
    public function __construct(Doku_Renderer $renderer) {
        $this->renderer = $renderer;
    }

    /**
     * @param string $tag
     * @param array<string, mixed> $params
     */
    public function opn(string $tag, ?string $css_class = null, ?array $params = null) : self {
        $this->stack[] = new HtmlContext($tag, $css_class, $params);
        return $this;
    }

    public function tag(string $tag, string $content, ?string $css_class = null, ?array $params = null) : self {
        $this->opn($tag, $css_class, $params);
        $this->add($content);
        $this->cls();

        return $this;
    }

    public function add(string $line) : self {
        $lastIndex = array_key_last($this->stack);
        if ($lastIndex === null) {
            $this->renderer->doc .= $line;
        }

        $this->stack[$lastIndex]->add($line);
        return $this;
    }

    public function cls() : self {
        $context = array_pop($this->stack);
        if ($context === null) {
            throw new RuntimeException('Cannot close an HTML context when none is open.');
        }

        $html = $context->build();
        $lastIndex = array_key_last($this->stack);

        if ($lastIndex === null) {
            $this->renderer->doc .= $html;
        } else {
            $this->stack[$lastIndex]->add($html);
        }

        return $this;
    }

    /**
     * Close every remaining context and write the resulting tree to the renderer.
     */
    public function flush() : self {
        while ($this->stack !== []) {
            $this->cls();
        }

        return $this;
    }
}
