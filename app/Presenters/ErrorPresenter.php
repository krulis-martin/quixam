<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Exceptions\BaseException;
use Nette;
use Nette\Application\Responses;
use Nette\Application\BadRequestException;
use Nette\Application\Helpers;
use Nette\Http;
use Tracy\ILogger;

final class ErrorPresenter implements Nette\Application\IPresenter
{
    use Nette\SmartObject;

    /** @var ILogger */
    private $logger;


    public function __construct(ILogger $logger)
    {
        $this->logger = $logger;
    }


    public function run(Nette\Application\Request $request): Nette\Application\Response
    {
        $exception = $request->getParameter('exception');
        $previousPresenter = $request->getParameter('previousPresenter');
        if ($previousPresenter && $previousPresenter instanceof RestPresenter) {
            if ($exception instanceof BaseException) {
                $previousPresenter->getHttpResponse()->setCode($exception->getHttpCode());
                return new Responses\JsonResponse([
                    'error' => [
                        'message' => $exception->getMessage(),
                        'code' => $exception->getCode(),
                    ],
                ]);
            }
        }

        if ($exception instanceof BadRequestException) {
            [$module,, $sep] = Helpers::splitName($request->getPresenterName());
            return new Responses\ForwardResponse($request->setPresenterName($module . $sep . 'Error4xx'));
        }

        $this->logger->log($exception, ILogger::EXCEPTION);
        return new Responses\CallbackResponse(function (Http\IRequest $httpRequest, Http\IResponse $httpResponse): void {
            if (preg_match('#^text/html(?:;|$)#', (string) $httpResponse->getHeader('Content-Type'))) {
                require __DIR__ . '/templates/Error/500.phtml';
            }
        });
    }
}
