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
        global $INPUT;

        if (!isset($nodePath[0])) {
            AbstractApi::error($out, 404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            AbstractApi::error($out, 404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }


        $params = [];
        // Use the second nodePath element as key if it exists
        if (isset($nodePath[1]) && $nodePath[1] !== null) {
            $params['id'] = $nodePath[1];
        }

        // get part id
        $partId = $INPUT->get->int('partId', null);
        if ($partId) {
            $params['partId'] = $partId;
        }

        $data = PlmMgrService::get()->getData($view, $params);

        // write the content
        $out->write(json_encode($data));
    }

    /**
     * POST /api/manager?view=master_data
     * 
     * @param Writer $out
     * @param array $nodePath
     */
    protected function handleWrite(Writer $out, array $nodePath): void {
        
        if (!isset($nodePath[0])) {
            AbstractApi::error($out, 404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            AbstractApi::error($out, 404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $key = null;
        // Use the second nodePath element as key if it exists
        if (isset($nodePath[1]) && $nodePath[1] !== null) {
            $key = $nodePath[1];
        }

        $json = file_get_contents('php://input');
        $inputData = json_decode($json, true);

        if (! $inputData) {
            AbstractApi::error($out, 400, "Invalid data sent.");
            return;
        }

        if ($key) {
            $inputData['id'] = $key;            
            $data = PlmMgrService::get()->update($view, $inputData);

        } else {
            if (isset($inputData['id'])) {
                unset($inputData['id']);
            }

            $data = PlmMgrService::get()->create($view, $inputData);
        }

        // write the content
        $out->write(json_encode($data));
    }
}
