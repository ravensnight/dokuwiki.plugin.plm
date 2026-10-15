<?php

if (!defined('DOKU_INC')) {
    die();
}

class PagesApi extends AbstractApi {

    /**
     * @param array $nodePath
     */
    #[Override]
    protected function handleRead(array $nodePath, array $params): void
    {
        if (!isset($nodePath[0])) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        $view = $nodePath[0];
        if ($view === null) {
            $this->terminate(404, "Invalid Content API path: " . implode('/', $nodePath));
            return;
        }

        // Load app main page.
        $file = PLM_HTML_DIR . '/' . $view .'.html';

        $content = file_get_contents($file);
        if ($content === null) {
            $this->terminate(500, "SubPage not defined for: " . implode('/', $nodePath));
        }

        // write the content
        $this->out->write($content);
    }
}
