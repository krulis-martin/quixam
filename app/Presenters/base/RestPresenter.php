<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\User;
use App\Security\AccessToken;
use App\Security\AccessTokenManager;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use ReflectionMethod;

/**
 * Base presenter for all REST API endpoints.
 */
class RestPresenter extends Presenter
{
    /** @var User|null */
    protected $user = null;

    /** @var AccessToken */
    protected $token = null;

    protected $body = null;

    /**
     * @inject
     * @var AccessTokenManager
     */
    public $accessTokenManager;

    protected function startup()
    {
        parent::startup();
        $this->token = $this->accessTokenManager->getGivenAccessToken($this->getHttpRequest());
        if ($this->token !== null) {
            $this->user = $this->accessTokenManager->getUser($this->token);
        }

        if (
            strtoupper($_SERVER['REQUEST_METHOD'] ?? '') == 'POST' &&
            strpos(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false
        ) {
            $body = file_get_contents('php://input');
            try {
                $this->body = $body ? Json::decode($body, true) : [];
            } catch (JsonException $e) {
                $this->error("Parsing of the JSON body failed: " . $e->getMessage(), 400);
            }
        }

        if (!$this->user) {
            $action = $this->getRequest()->getParameter('action');
            if ($action === 'getToken') {
                // this is the only exception in all REST endpoints
                return; // skip the checks
            }
            $this->error("The endpoint requires valid authentication token.", 403);
        }

        if (!$this->user->isAdmin()) {
            // users (except for admin) should check the access permissions
            // (if corresponding 'check' method exists)
            $params = $this->getRequest()->getParameters();
            $checkMethod = "check" . ucfirst($params['action'] ?? '');
            if (method_exists($this, $checkMethod)) {
                $args = [];
                $refMethod = new ReflectionMethod($this, $checkMethod);
                foreach ($refMethod->getParameters() as $p) {
                    $name = $p->getName();
                    $args[] = $params[$name] ?? null;
                }

                if (!$this->$checkMethod(...$args)) {
                    $this->error("The user does not have sufficient privileges to access selected content.", 403);
                }
            }
        }
    }

    protected function sendSuccessResponse($payload, $code = 200)
    {
        $this->getHttpResponse()->setCode($code);
        $this->sendJson(
            [
                "success" => true,
                "code" => $code,
                "payload" => $payload
            ]
        );
    }
}
