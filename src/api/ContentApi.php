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
    #[Override]
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
    #[Override]
    protected function handleCreate(array $nodePath, array $params): void {
        
        if (count($nodePath) !== 1) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $json = file_get_contents('php://input');
        $inputData = json_decode($json, true);

        if (! $inputData) {
            $this->terminate(400, "Invalid data sent.");
            return;
        }

        if (isset($inputData['id'])) {
            unset($inputData['id']);
        }

        $data = PlmMgrService::get()->create($view, $inputData);

        // write the content
        $this->out->write(json_encode($data));
    }

    /**
     * @param array $nodePath
     */
    #[Override]
    protected function handleUpdate(array $nodePath, array $params): void
    {

        if (count($nodePath) !== 2) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        // Use the second nodePath element as key 
        $key = $nodePath[1];
        if ($key === null) {
            $this->terminate(400, "Key for element to update not defined: " . implode('/', $nodePath));
            return;
        }

        $json = file_get_contents('php://input');
        $inputData = json_decode($json, true);

        if (! $inputData) {
            $this->terminate(400, "Invalid data sent.");
            return;
        }

        $inputData['id'] = $key;
        $data = PlmMgrService::get()->update($view, $inputData);

        // write the content
        $this->out->write(json_encode($data));
    }

    /**
     * @param array $nodePath
     */
    #[Override]
    protected function handleDelete(array $nodePath, array $params): void
    {
        if (count($nodePath) !== 2) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        // Use the second nodePath element as key 
        $key = $nodePath[1];
        if ($key === null) {
            $this->terminate(400, "Key for element to update not defined: " . implode('/', $nodePath));
            return;
        }

        $inputData['id'] = $key;
        PlmMgrService::get()->delete($view, $inputData);

        // write the content
        $this->out->write(json_encode(null));
    }    
}
