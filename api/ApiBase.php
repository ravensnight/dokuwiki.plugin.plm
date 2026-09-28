<?php

use League\CommonMark\Renderer\HtmlRenderer;

if (!defined('DOKU_INC')) {
    die();
}

abstract class ApiBase
{
    private readonly PlmDB $db;

    public function __construct(PlmDB $db) {
        $this->db = $db;
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

    public static function error(int $status, string $message): never
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

    /**
     * Create a standard form group with label and input field
     * @param HtmlBuilder $out
     * @param string $label
     * @param string $name
     * @param string $value
     * @param bool $readonly
     */
    protected function formGroup(HtmlBuilder $out, string $label, string $name, string $value, bool $readonly = false)
    {
        $params = [];
        if ($readonly) {
            $params[ 'readonly' ] = 'readonly';
        }

        $out->opn('div', 'form-group');
        
        $this->label($out, $label);
        $this->input($out, 'text', $value, $name, $params);

        $out->cls(); // Close div
    }

    /**
     * Create a category selection form group
     * @param HtmlBuilder $out
     * @param string $label
     * @param string $name
     * @param Category[] $categories
     * @param mixed $selectedCategoryId
     */
    protected function createCategoryFormGroup(HtmlBuilder $out, string $label, string $name, array $categories, $selectedCategoryId)
    {
        $out->opn('div', 'form-group');
        $this->label($out, $label);
        
        $out->opn('select', null, [
            'name' => $name,
        ]);

        foreach ($categories as $category) {
            $params = [
                'value' => $category->pk,
            ];

            if ($category->pk === $selectedCategoryId) {
                $params['selected'] = 'selected';
            }

            $out->tag('option', $category->description . ' (' . $category->name . ')', null, $params);
        }

        $out->cls() // Close select
        ->cls(); // Close div
    }

    /**
     * Create an HTML input tag with common attributes
     * @param HtmlBuilder $out
     * @param string $type
     * @param string $value
     * @param string $name (optional)
     */
    protected function input(HtmlBuilder $out, string $type, string $value, string $name, ?array $p = null)
    {
        $params = [
            'type' => $type,
            'name' => $name,
            'value' => $value
        ];

        if ($p !== null) {
            $params += $p;
        }

        $out->tag('input', null, null, $params);
    }

    /**
     * Create a label element
     * @param HtmlBuilder $out
     * @param string $text
     */
    protected function label(HtmlBuilder $out, string $text)
    {
        $out->tag('label', $text);
    }

    public static function handleRequest(PlmDB $db, string $path): void {

        $method = strtolower( $_SERVER['REQUEST_METHOD'] ?? 'get' );
        if (preg_match('#^v1/([^/]+)(?:/(.+))?$#', $path, $match)) {

            $api = null;
            $apiName = $match[1];
            $nodePath = [];

            if (isset($match[2]) && $match[2] !== '') {
                $nodePath = explode('/', $match[2]);
            }

            switch ($apiName) {

                case 'parts':
                    $api = new ApiPart($db);
                    break;

                case 'versions':
                    $api = new ApiVersion($db);
                    break;

                default:
                    ApiBase::error(404, 'PLM API not found: ' . $apiName);
                    break;
            }

             if ($api !== null) {
                $api->handle($method, $nodePath);
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

        /** @var ResponseWriter w */
        $w = new ResponseWriter();

        /** @var HtmlBuilder out */
        $out = new HtmlBuilder();
        
        $evt = null;
        switch ($method) {
            case 'get' :
                $evt = $this->doGet($out, $nodePath);
                break;

            case 'post':
                $evt = $this->doPost($out, $nodePath);
                break;

            default:
                $this->methodNotAllowed();
        }

        if ($evt !== null) {
            $w->additionalHeaders['hx-trigger'] = '{"' . $evt->name . '":' . json_encode($evt->details ?? 'null') . '}';
        }

        /**
         * @var ResponseWriter w
         */
        $out->flush($w);
    }

    /**
     * @return Event - a html event to trigger
     */
    protected abstract function doGet(HtmlBuilder $out, array $nodePath) : Event;

    /**
     * @return Event - a html event to trigger
     */
    protected abstract function doPost(HtmlBuilder $out, array $nodePath): Event;
}
