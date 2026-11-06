<?php

declare(strict_types=1);

namespace App;

use App\Config\Config;
use App\Controller\PostController;
use App\Controller\ProfileController;
use App\Controller\UserController;
use App\Database\Database;
use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repository\PostRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use LogicException;
use PDOException;
use Throwable;

/**
 * Punto central de la aplicación: define las rutas, construye los controladores
 * y convierte cualquier excepción en una respuesta JSON coherente.
 */
final class Application
{
    public const VERSION = '2.0.0';

    private Router $router;
    private bool $debug;

    public function __construct()
    {
        $this->debug = Config::bool('APP_DEBUG');
        $this->router = new Router(fn (string $class): object => $this->make($class));
        $this->registerRoutes();
    }

    public function handle(Request $request): Response
    {
        return $this->withCors($this->process($request));
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        $r->get('/', static fn (Request $request, array $params): Response => Response::ok([
            'name' => 'php-api',
            'version' => self::VERSION,
            'resources' => ['users', 'users/{id}/profile', 'users/{id}/posts', 'posts'],
        ]));

        // Usuarios
        $r->get('/users', [UserController::class, 'index']);
        $r->post('/users', [UserController::class, 'store']);
        $r->get('/users/{id}', [UserController::class, 'show']);
        $r->put('/users/{id}', [UserController::class, 'update']);
        $r->patch('/users/{id}', [UserController::class, 'update']);
        $r->delete('/users/{id}', [UserController::class, 'destroy']);

        // Perfil del usuario (1:1)
        $r->get('/users/{id}/profile', [ProfileController::class, 'show']);
        $r->put('/users/{id}/profile', [ProfileController::class, 'upsert']);
        $r->patch('/users/{id}/profile', [ProfileController::class, 'upsert']);
        $r->delete('/users/{id}/profile', [ProfileController::class, 'destroy']);

        // Posts del usuario (1:N)
        $r->get('/users/{id}/posts', [PostController::class, 'indexByUser']);
        $r->post('/users/{id}/posts', [PostController::class, 'store']);

        // Posts
        $r->get('/posts', [PostController::class, 'index']);
        $r->get('/posts/{id}', [PostController::class, 'show']);
        $r->put('/posts/{id}', [PostController::class, 'update']);
        $r->patch('/posts/{id}', [PostController::class, 'update']);
        $r->delete('/posts/{id}', [PostController::class, 'destroy']);
    }

    /** Construye un controlador con sus dependencias (la conexión a la BD se abre aquí, solo si hace falta). */
    private function make(string $class): object
    {
        $pdo = Database::connection();
        $users = new UserRepository($pdo);
        $profiles = new ProfileRepository($pdo);
        $posts = new PostRepository($pdo);

        return match ($class) {
            UserController::class => new UserController($pdo, $users, $profiles, $posts),
            ProfileController::class => new ProfileController($users, $profiles),
            PostController::class => new PostController($users, $posts),
            default => throw new LogicException("Unknown controller: $class"),
        };
    }

    private function process(Request $request): Response
    {
        if ($request->method() === 'OPTIONS') {
            return new Response(null, 204);
        }

        try {
            return $this->router->dispatch($request);
        } catch (ApiException $e) {
            return Response::error($e->getStatusCode(), $e->getMessage(), $e->getErrors(), $e->getHeaders());
        } catch (PDOException $e) {
            error_log('[php-api] Database error: ' . $e->getMessage());

            // SQLSTATE 23000: violación de integridad (UNIQUE, FOREIGN KEY...), por ejemplo en condiciones de carrera.
            if ((string) $e->getCode() === '23000') {
                return Response::error(409, 'The operation violates a data integrity constraint');
            }

            return Response::error(500, 'Database error', $this->debugInfo($e));
        } catch (Throwable $e) {
            error_log('[php-api] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return Response::error(500, 'Internal server error', $this->debugInfo($e));
        }
    }

    /** @return array<string, string> */
    private function debugInfo(Throwable $e): array
    {
        return $this->debug ? ['debug' => $e->getMessage()] : [];
    }

    private function withCors(Response $response): Response
    {
        $origin = Config::get('CORS_ORIGIN');

        if ($origin === null || $origin === '') {
            return $response;
        }

        return $response->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }
}
