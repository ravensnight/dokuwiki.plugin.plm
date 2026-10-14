<?php 

class RenderContext {

    public ?string $type;
    public mixed $config;

    protected function __construct(?string $type = null, mixed $config = null) {
        $this->type = $type;
        $this->config = $config;
    }

    public static function create() : self {
        return new RenderContext();
    }

    public function parse(string $macroContent) : void {
        
        $this->clear();
        if ($macroContent !== null) {

            // clear line breaks
            $content = str_replace(["\r\n", "\r"], "\n", $macroContent);
            $pos = strpos($content, "\n");  // search first line end

            if ($pos === false) {
                $this->type = trim($content);
            } else {
                $this->type = trim(substr($content, 0, $pos));
                
                $data = substr($content, $pos + 1);
                $this->config = json_decode($data, true);
            }
        }
    }

    public function clear() {
        $this->type = null;
        $this->config = null;
    } 
}