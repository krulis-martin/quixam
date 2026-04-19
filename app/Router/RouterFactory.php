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

        $router[] = new GetRoute('rest/templates/test/<id>', 'RestTemplates:getTest');
        $router[] = new PostRoute('rest/templates/test/<id>/grading', 'RestTemplates:setGrading');
        $router[] = new PostRoute('rest/templates/test/<id>/group/<groupId>', 'RestTemplates:addGroup');
        $router[] = new DeleteRoute('rest/templates/test/<id>/group/<groupId>', 'RestTemplates:deleteGroup');
        $router[] = new PostRoute(
            'rest/templates/test/<id>/group/<groupId>/question/<questionId>',
            'RestTemplates:addQuestion'
        );
        $router[] = new DeleteRoute(
            'rest/templates/test/<id>/group/<groupId>/question/<questionId>',
            'RestTemplates:deleteQuestion'
        );

        $router[] = new GetRoute('rest/terms', 'RestTerms:default');
        $router[] = new PostRoute('rest/terms/<testId>', 'RestTerms:addTerm');
        $router[] = new DeleteRoute('rest/term/<id>', 'RestTerms:removeTerm');
        $router[] = new PostRoute('rest/term/<id>/users', 'RestTerms:registerUsers');

        // routes for app presenters
        $router->addRoute('<presenter>/<action>[/<id>]', [
            'presenter' => 'Homepage',
            'action' => 'default',
            'id' => null,
        ]);
        return $router;
    }
}
