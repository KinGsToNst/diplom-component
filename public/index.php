<?php
if( !session_id() ) {
    session_start();
}
require '../vendor/autoload.php';
use App\Controllers\UserController;
use App\Models;
use App\Models\UserBuilder;
use Aura\SqlQuery\QueryFactory;
use Delight\Auth\Auth;
use League\Plates\Engine;
use App\Controllers\QueryBuilder;



$builder = new DI\ContainerBuilder();


//PHP DI
$builder->addDefinitions([
    Engine::class => function() {
        return new Engine(__DIR__ . '/views');
    },
    PDO::class => function() {
        $host = 'localhost'; // имя сервера базы данных
        $dbname = 'component'; // имя базы данных
        $username = 'root'; // имя пользователя базы данных
        $pass = 'root'; // пароль пользователя базы данных
        return new PDO("mysql:host=$host;dbname=$dbname", $username, $pass);
    },
    Auth::class => function(\PDO $pdo) { // Внимание изменение здесь
        return new Auth($pdo);
    },
    QueryFactory::class => function() {
        return new QueryFactory('mysql');
    }
]);
try {
    $container = $builder->build();
} catch (Exception $e) {
}

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {

    $r->addRoute('GET', '/users', ['App\Controllers\UserController','getAllUsers']);
    $r->addRoute(['GET', 'POST'], '/login', ['App\Controllers\UserController','login']);

    $r->addRoute('GET', '/page_register', ['App\Controllers\UserController','pageRegister']);
    $r->addRoute('POST', '/register', ['App\Controllers\UserController','register']);

    $r->addRoute('GET', '/create_user', ['App\Controllers\UserController', 'pageCreateUser']);
    $r->addRoute('POST', '/create', ['App\Controllers\UserController','createUser']);

    $r->addRoute('GET', '/edit_user/{id:\d+}', ['App\Controllers\UserController', 'editUser']);
    $r->addRoute('POST', '/update_user/{id:\d+}', ['App\Controllers\UserController', 'updateUser']);

    $r->addRoute('GET', '/status/{id:\d+}', ['App\Controllers\UserController', 'editStatus']);
    $r->addRoute('POST', '/update_status/{id:\d+}', ['App\Controllers\UserController', 'updateStatus']);


    $r->addRoute('GET', '/media/{id:\d+}', ['App\Controllers\UserController', 'editMedia']);
    $r->addRoute('POST', '/update_media/{id:\d+}', ['App\Controllers\UserController', 'updateMedia']);

    $r->addRoute('GET', '/security/{id:\d+}', ['App\Controllers\UserController', 'editSecurity']);
    $r->addRoute('POST', '/update_security/{id:\d+}', ['App\Controllers\UserController', 'updateSecurity']);

    $r->addRoute('GET', '/delete_user/{id:\d+}', ['App\Controllers\UserController', 'deleteUser']);

    $r->addRoute(['GET'], '/logout', ['App\Controllers\UserController','logout']);

});

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // ... 404 Not Found
        echo '404';
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        $class=$handler[0];//будет класс
        $method=$handler[1];
        $container->call($routeInfo[1],$routeInfo[2]);
       // d($handler,$vars);
        // ... call $handler with $vars
        break;

}

