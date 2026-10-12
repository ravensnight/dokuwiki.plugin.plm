<?php

abstract class AbstractApi
{
    private readonly ResponseWriter $out;

    public function __construct(ResponseWriter $out) {
        $this->out = $out;
    }

    protected function out() : ResponseWriter {
        return $this->out;
    }

    public static function error(ResponseWriter $out, int $status, string $message): never
    {
        /**
         * @var HtmlBuilder out
         */
        $html = new HtmlBuilder();
        $out->statusCode = $status;

        $html->opn('p', 'error, error' . ((string)$status));
        $html->tag('span', (string)$status, 'code');
        $html->tag('span', $message, 'message');

        $html->cls()->flush($out);
        $out->flush();
        
        exit;
    }

    protected function requireAuthentication(): void
    {
        global $INPUT;
        $user = $INPUT->server->str('REMOTE_USER');
        if ($user === '') {
            AbstractApi::error($this->out, 401, 'Authentication required');
        }
    }

    protected function currentUser(): string
    {
        global $INPUT;
        return $INPUT->server->str('REMOTE_USER');
    }

    protected function currentGroups(): array
    {
        global $USERINFO;
        if (!is_array($USERINFO)) {
            return [];
        }

        return $USERINFO['grps'] ?? [];
    }

    protected function requireRole(string $role): void
    {
        $roles = [
            'guest' => [], // non-authenticated reader
            'reader' => ['plm_read','plm_editor','plm_admin' ], // authenticated reader
            'author' => [ 'plm_editor', 'plm_admin' ], // author
            'admin' => [ 'plm_admin' ] // administrator
        ];

        if (!isset($roles[$role])) {
            AbstractApi::error($this->out, 500, 'Invalid API role' );
        }

        if (count($roles[$role]) == 0) {
            return; // allow guest users.
        }

        $this->requireAuthentication();
        $groups = $this->currentGroups(); // groups from current user.

        foreach ($roles[$role] as $group) {
            if (in_array($group, $groups, true)) {
                return;
            }
        }

        AbstractApi::error($this->out, 403, 'Forbidden' );
    }

    protected function notFound(): never {
        AbstractApi::error($this->out, 404, 'Not found' );
    }

    protected function methodNotAllowed(string $method): never {
        AbstractApi::error($this->out, 405, 'Method ' . $method . ' not allowed!' );
    }

    /**
     * Request handler
     */
    public static function handleRequest(string $uriPath): void
    {
        /** @var ResponseWriter $w */
        $w = new ResponseWriter();

        if (preg_match('#^/api/v1/([^/]+)(?:/(.+))?$#', $uriPath, $match)) {

            $api = null;
            $apiName = $match[1];
            $nodePath = [];

            if (isset($match[2]) && $match[2] !== '') {
                $nodePath = explode('/', $match[2]);
            }

            switch ($apiName) {

                case 'pages':
                    $api = new PagesApi($w);
                    break;

                case 'content':
                    $api = new ContentApi($w);
                    break;

                default:
                    AbstractApi::error($w, 404, 'PLM API not found: ' . $apiName);
                    return;
            }

            // Get method and call api.
            $method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
            if ($api !== null) {
                $api->handle($method, $nodePath);
            }

            return;
        } else {
            AbstractApi::error($w, 404, 'API endpoint not found: ' . var_export($uriPath, true));
            return;
        }
    }

    /**
     * Handle the api call.
     * @param string $method
     * @param string[] $nodePath
     */
    protected final function handle(string $method, array $nodePath) {

        /** @var ResponseWriter w */
        $out = new ResponseWriter();
        
        switch ($method) {
            case 'get' :
                $this->handleRead($out, $nodePath);
                break;

            case 'post':
                $this->handleWrite($out, $nodePath);
                break;

            default:
                $this->methodNotAllowed($method);
        }

        $out->flush();
    }

    /**
     * Handle some get request.
     */
    protected abstract function handleRead(ResponseWriter $out, array $nodePath) : void;

    /**
     * Handle some post request.
     */
    protected abstract function handleWrite(ResponseWriter $out, array $nodePath): void;

}
