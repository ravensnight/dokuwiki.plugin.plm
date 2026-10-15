<?php

abstract class AbstractApi
{

    protected readonly ResponseWriter $out;

    public function __construct(ResponseWriter $out) {
        $this->out = $out;
    }

    public static function halt(ResponseWriter $out, int $status, string $message, ?array $trace = null) : never {
        $out->statusCode = $status;

        $res = [
            "status" => $status,
            "message" => $message,
            "trace" => $trace
        ];

        $out->write(json_encode($res));
        $out->flush();

        exit;
    }

    public function terminate(int $status, string $message, ?array $trace = null): never
    {
        AbstractApi::halt($this->out, $status, $message, $trace);
    }

    protected function requireAuthentication(): void {
        global $INPUT;
        $user = $INPUT->server->str('REMOTE_USER');
        if ($user === '') {
            $this->terminate(401, 'Authentication required');
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
            $this->terminate(500, 'Invalid API role' );
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

        $this->terminate(403, 'Forbidden' );
    }

    protected function notFound(): never {
        $this->terminate(404, 'Not found' );
    }

    protected function methodNotAllowed(string $method): never {
        $this->terminate(405, 'Method ' . $method . ' not allowed!' );
    }

    /**
     * Request handler
     */
    public static function handleRequest(array $path, array $params): void
    {
        /** @var ResponseWriter $w */
        $w = new ResponseWriter();

        $api = null;
        $apiName = $path[0];

        $nodePath = array_slice($path, 1); // cut the api name
        
        switch ($apiName) {

            case 'pages':
                $api = new PagesApi($w);
                break;

            case 'content':
                $api = new ContentApi($w);
                break;

            default:
                AbstractApi::halt($w, 404, 'PLM API not found: ' . $apiName);
                return;
        }

        // Get method and call api.
        $method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
        if ($api !== null) {
            $api->handle($method, $nodePath, $params);
        }

        $w->statusCode = 200;
        $w->flush();
        return;
    }

    /**
     * Handle the api call.
     * @param string $method
     * @param string[] $nodePath
     */
    protected final function handle(string $method, array $nodePath, array $params) {
        
        try {

            switch ($method) {
                case 'get' :
                    $this->handleRead($nodePath, $params);
                    break;

                case 'post':
                    $this->handleCreate($nodePath, $params);
                    break;

                case 'put':
                    $this->handleUpdate($nodePath, $params);
                    break;

                case 'delete':
                    $this->handleDelete($nodePath, $params);
                    break;

                default:
                    $this->methodNotAllowed($method);
            }

        } catch (Exception $e) {
            $this->terminate(400, $e->getMessage(), $e->getTrace());
        }

    }

    /**
     * Handle some get request.
     */
    protected function handleRead(array $nodePath, array $params) : void {
        $this->terminate(400, 'Function not supported.');
    }

    /**
     * Handle some create request.
     */
    protected function handleCreate(array $nodePath, array $params): void {
        $this->terminate(400, 'Function not supported.');
    }

    /**
     * Handle some update request.
     */
    protected function handleUpdate(array $nodePath, array $params): void {
        $this->terminate(400, 'Function not supported.');
    }

    /**
     * Handle some delete request.
     */
    protected function handleDelete(array $nodePath, array $params): void {
        $this->terminate(400, 'Function not supported.');
    }
}
