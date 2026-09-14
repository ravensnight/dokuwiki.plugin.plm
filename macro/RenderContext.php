<?php 

class RenderContext {

    public PlmState $state;
    public ?string $header;
    public ?string $body;

    protected function __construct(PlmState $plmState, ?string $header, ?string $body) {
        $this->state = $plmState;
        $this->header = $header;
        $this->body = $body;
    }

    public static function createEmpty(PlmState $plmState) : self {
        return new RenderContext($plmState, null, null);
    }

    public function fromMacroContent(string $macroContent) {
        if ($this->header === null) {
            $content = str_replace(["\r\n", "\r"], "\n", $macroContent);
            $pos = strpos($content, "\n");  // search first line end

            if ($pos === false) {
                $this->header = trim($content);
            } else {
                $this->header = trim(substr($content, 0, $pos));
                $this->body = substr($content, $pos + 1);
            }
        } else {
            $this->body = $macroContent;
        }
    }

    public function clear() {
        $this->header = null;
        $this->body = null;
    }  
}