<?php

if (!defined('DOKU_INC')) {
    die();
}

class PagesApi extends AbstractApi {

    /**
     * GET /api/pages?view={parts|versions|bom|products|companies|masterdata}
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

        // Load app main page.
        $file = PLM_PAGES_DIR . '/' . $view .'.html';

        $content = file_get_contents($file);
        if ($content === null) {
            AbstractApi::error($out, 500, "SubPage not defined for: " . implode('/', $nodePath));
        }

        // write the content
        $out->write($content);
    }

    /**
     * POST /api/manager?view=master_data
     * 
     * @param Writer $out
     * @param array $nodePath
     */
    protected function handleWrite(ResponseWriter $out, array $nodePath): void
    {
        $this->error($this->out(), 400, "Function not supported.");
    }
}
