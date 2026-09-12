<?php

class PartVersion extends DbObject {
    
    public string $versionId;
    public PK $part;

    public int $major = -1;
    public int $revision = -1;

    public ?string $description = null;
    public ?string $status;

    /** @var ItemRef[] */
    public ?array $subItems = null;
    
    public function __construct(int $primaryKey, string $versionId, int $partId)
    {
        parent::__construct($primaryKey);
        $this->versionId = $versionId;
        $this->part = new PK($partId);
    }
}
