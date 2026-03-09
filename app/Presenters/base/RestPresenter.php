<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\User;
use App\Security\AccessToken;
use App\Security\AccessTokenManager;
use App\Exceptions\BadRequestException;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Base presenter for all REST API endpoints.
 */
class RestPresenter extends Presenter
{
    /** @var User|null */
    protected $user = null;

    /** @var AccessToken */
    protected $token = null;

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
                $json = $body ? Json::decode($body, true) : [];
            } catch (JsonException $e) {
                throw new BadRequestException("Parsing of the JSON body failed: " . $e->getMessage());
            }

            if (!is_array($json)) {
                throw new BadRequestException("A collection is expected as JSON body. Scalar value was given instead.");
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
