<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

/**
 * Router mínimo: asocia (método, patrón de ruta) con un manejador.
 *
 * Los patrones admiten parámetros numéricos con llaves: "/users/{id}/posts".
 * Un manejador puede ser un Closure o un array [ClaseControlador::class, 'metodo'];
 * en este último caso el controlador se construye con el resolver recibido.
 * Todos los manejadores reciben (Request $request, array $params) y devuelven Response.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: array|Closure}> */
    private array $routes = [];

    /** @param Closure(string): object $resolver Construye un controlador a partir de su nombre de clase. */
    public function __construct(private readonly Closure $resolver)
    {
    }

    public function get(string $pattern, array|Closure $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array|Closure $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, array|Closure $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, array|Closure $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, array|Closure $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function add(string $method, string $pattern, array|Closure $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[0-9]+)', $pattern);

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    /**
     * @throws ApiException 404 si ninguna ruta coincide; 405 si coincide la ruta pero no el método.
     */
    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = (int) $value;
                }
            }

            return $this->call($route['handler'], $request, $params);
        }

        if ($allowedMethods !== []) {
            throw ApiException::methodNotAllowed($allowedMethods);
        }

        throw ApiException::notFound('Route not found');
    }

    /** @param array<string, int> $params */
    private function call(array|Closure $handler, Request $request, array $params): Response
    {
        if ($handler instanceof Closure) {
            return $handler($request, $params);
        }

        [$class, $method] = $handler;
        $controller = ($this->resolver)($class);

        return $controller->$method($request, $params);
    }
}
