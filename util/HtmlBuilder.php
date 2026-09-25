<?php 

class HtmlBuilder {

    private array $stack = [];
    private string $buffer = '';

    /**
     * Constructor
     */
    public function __construct() {
    }

    /**
     * @param string $tag
     * @param array<string, mixed> $params
     */
    public function opn(string $tag, ?string $css_class = null, ?array $params = null) : self {
        $this->stack[] = new HtmlContext($tag, $css_class, $params);
        return $this;
    }

    public function tag(string $tag, ?string $content, ?string $css_class = null, ?array $params = null) : self {
        $this->opn($tag, $css_class, $params);
        if ($content !== null) {
            $this->add($content);
        }
        $this->cls();

        return $this;
    }

    public function add(string $line) : self {
        $lastIndex = array_key_last($this->stack);
        $context = null;

        if ($lastIndex === null) {
            $this->buffer .= $line;
        } else {
            $this->stack[$lastIndex]->add($line);
        }

        return $this;
    }

    public function cls() : self {
        $context = array_pop($this->stack);
        if ($context === null) {
            throw new RuntimeException('Cannot close an HTML context when none is open.');
        }

        $html = $context->build();
        return $this->add($html);
    }

    /**
     * Close every remaining context and write the resulting tree to the renderer.
     */
    public function flush(Writer $writer) : self {
        while ($this->stack !== []) {
            $this->cls();
        }

        $writer->write($this->buffer);
        return $this;
    }
}
