<?php

class MacroHeader {
    public readonly string $macro;
    public readonly string $context;
    public readonly ?string $reference;
    public readonly ?string $filter;
    public readonly ?string $message;

    public function __construct(
        string $macro,
        string $context,
        ?string $reference = null,
        ?string $filter = null,
        ?string $message = null
    )
    {
        $this->macro = $macro;
        $this->context = $context;
        $this->reference = $reference;
        $this->filter = $filter;
        $this->message = $message ?? 'Not found!';
    }
}