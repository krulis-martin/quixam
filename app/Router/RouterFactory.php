<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    use Nette\StaticClass;

    public static function createRouter(): RouteList
    {
        $router = new RouteList();

        // REST API routes
        $router[] = new PostRoute('rest/login', 'RestLogin:getToken');
        $router[] = new PostRoute('rest/refresh', 'RestLogin:refreshToken');

        // routes for app presenters
        $router->addRoute('<presenter>/<action>[/<id>]', [
            'presenter' => 'Homepage',
            'action' => 'default',
            'id' => null,
        ]);
        return $router;
    }
}
