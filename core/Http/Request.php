<?php
declare(strict_types=1);

namespace Core\Http;

final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $queryParams,
        private readonly array $bodyParams,
        private readonly array $headers,
        private readonly string $rawBody,
    ) {}

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($path === '') {
            $path = '/';
        }

        // Support du montage applicatif sous un sous-chemin (ex: /pilora).
        // Si l'app est accessible via http(s)://localhost/pilora/, alors REQUEST_URI
        // vaut souvent "/pilora/..." et notre routeur attend des chemins internes
        // comme "/" ou "/clients".
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = dirname($scriptName);
        $scriptDir = $scriptDir === '\\' ? '' : str_replace('\\', '/', $scriptDir);
        $scriptDir = rtrim($scriptDir, '/');

        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir . '/')) {
            $path = substr($path, strlen($scriptDir));
        } elseif ($scriptDir !== '' && $scriptDir !== '/' && $path === $scriptDir) {
            $path = '/';
        }

        $queryParams = $_GET ?? [];

        $rawBody = file_get_contents('php://input');
        $bodyParams = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($rawBody ?: '', true);
                $bodyParams = is_array($decoded) ? $decoded : [];
            } else {
                $bodyParams = $_POST ?? [];
            }
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];

        return new self(
            method: $method,
            path: $path,
            queryParams: is_array($queryParams) ? $queryParams : [],
            bodyParams: is_array($bodyParams) ? $bodyParams : [],
            headers: is_array($headers) ? $headers : [],
            rawBody: is_string($rawBody) ? $rawBody : '',
        );
    }

    /** Corps brut (ex. vérification signature webhook Stripe). */
    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getBodyParam(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $default;
    }

    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    public function getHeader(string $key, mixed $default = null): mixed
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp((string) $k, $key) === 0) {
                return $v;
            }
        }
        return $default;
    }
}

