<?php

class ContentApi extends AbstractApi {

    #[Override]
    function __construct(ResponseWriter $out)
    {
        parent::__construct($out);        
    }

    /**
     * @param array $nodePath
     */
    protected function handleRead(array $nodePath, array $params): void
    {
        global $INPUT;

        if (!isset($nodePath[0])) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $p = $params;
        // Use the second nodePath element as key if it exists
        if (isset($nodePath[1]) && $nodePath[1] !== null) {
            $p['id'] = $nodePath[1];
        }

        // acquire data
        $data = PlmMgrService::get()->getData($view, $p);

        // write the content
        $this->out->write(json_encode($data));
    }

    /**
     * @param array $nodePath
     */
    protected function handleWrite(array $nodePath, array $params): void {
        
        if (!isset($nodePath[0])) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
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
            $this->terminate(400, "Invalid data sent.");
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
        $this->out->write(json_encode($data));
    }
}
