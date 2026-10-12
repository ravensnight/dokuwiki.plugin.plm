<?php

class ContentApi extends AbstractApi {

    #[Override]
    function __construct(ResponseWriter $out)
    {
        parent::__construct($out);
        
    }

    /**
     * GET /api/manager?view={parts|versions|bom|products|companies|master_data}
     * 
     * @param Writer $out
     * @param array $nodePath
     */
    protected function handleRead(Writer $out, array $nodePath): void
    {
        if (!isset($nodePath[0])) {
            AbstractApi::error($out, 404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            AbstractApi::error($out, 404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $data = PlmMgrService::get()->getData($view);

        // write the content
        $out->write(json_encode($data));
    }

    /**
     * POST /api/manager?view=master_data
     * 
     * @param Writer $out
     * @param array $nodePath
     * @return Event
     */
    protected function handleWrite(Writer $out, array $nodePath): void {

    }
}
