<?php

use League\CommonMark\Renderer\HtmlRenderer;

if (!defined('DOKU_INC')) {
    die();
}

abstract class ApiBase
{
    private readonly PlmDB $db;     
    private readonly HtmlBuilder $out;

    public function __construct(PlmDB $db) {
        $this->db = $db;
        $this->out = new HtmlBuilder();
    }

    protected function db() : PlmDB {
        return $this->db;
    }

    protected function requireAuthentication(): void
    {
        global $INPUT;
        $user = $INPUT->server->str('REMOTE_USER');
        if ($user === '') {
            $this->error( 401, 'Authentication required');
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

    protected static function error(int $status, string $message): never
    {
        /**
         * @var HtmlBuilder out
         */
        $out = new HtmlBuilder();
        $reply = new ResponseWriter();
        $reply->statusCode = $status;

        $out->opn('p', 'error, error' . ((string)$status));
        $out->tag('span', (string)$status, 'code');
        $out->tag('span', $message, 'message');

        $out->cls()->flush($reply);
        exit;
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
            ApiBase::error(500, 'Invalid API role' );
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

        ApiBase::error(403, 'Forbidden' );
    }

    protected function notFound(): never {
        ApiBase::error(404, 'Not found' );
    }

    protected function methodNotAllowed(): never {
        ApiBase::error(405, 'Method not allowed' );
    }

    public static function handleRequest(PlmDB $db, string $path): void {

        $method = strtolower( $_SERVER['REQUEST_METHOD'] ?? 'get' );
        if (preg_match('#^v1/([^/]+)(?:/(.+))?$#', $path, $match)) {

            $apiName = $match[1];
            $nodePath = [];

            if (isset($match[2]) && $match[2] !== '') {
                $nodePath = explode('/', $match[2]);
            }

            switch ($apiName) {

                case 'parts':
                    $api = new ApiPart($db);
                    $api->handle($method, $nodePath);
                    break;

                default:
                    ApiBase::error(404, 'PLM API not found: ' . $apiName);
                    break;
            }

            return;
        } else {
            ApiBase::error(404, 'API endpoint not found: ' . var_export($path, true));
        }
    }

    /**
     * Handle the api call.
     * @param string $method
     * @param string[] $nodePath
     */
    protected final function handle(string $method, array $nodePath) {

        /**
         * @var HtmlBuilder out
         */
        $out = new HtmlBuilder();

        switch ($method) {
            case 'get' : 
                $this->doGet($out, $nodePath);
                break;

            case 'post':
                $this->doCreate($out, $nodePath);
                break;

            case 'put':
                $this->doUpdate($out, $nodePath);
                break;

            case 'delete':
                $this->doDelete($out, $nodePath);
                break;

            default:
                $this->methodNotAllowed();                
        }

        /**
         * @var ResponseWriter w
         */
        $w = new ResponseWriter();
        $out->flush($w);
    }

    protected abstract function doGet(HtmlBuilder $out, array $nodePath) : void;
    protected abstract function doUpdate(HtmlBuilder $out, array $nodePath): void;
    protected abstract function doCreate(HtmlBuilder $out, array $nodePath): void;
    protected abstract function doDelete(HtmlBuilder $out, array $nodePath): void;
}

