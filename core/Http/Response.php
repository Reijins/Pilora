<?php
declare(strict_types=1);

namespace Core\Http;

use Core\Config;

final class Response
{
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {}

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }

    public static function redirect(string $location, int $status = 302): self
    {
        // Gestion du montage applicatif sous un sous-chemin (ex: /pilora).
        // On normalise toutes les redirections internes pour conserver le basePath.
        $location = (string) $location;

        // Si URL absolue, on laisse tel quel.
        if (preg_match('/^https?:\/\//i', $location) === 1) {
            return new self('', $status, ['Location' => $location]);
        }

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = dirname($scriptName);
        $scriptDir = str_replace('\\', '/', $scriptDir);
        $scriptDir = rtrim($scriptDir, '/');
        if ($scriptDir === '.' || $scriptDir === '\\' || $scriptDir === '') {
            $scriptDir = '';
        }

        // Normalisation du chemin cible.
        $path = $location;
        if (str_starts_with($path, '/')) {
            $path = ltrim($path, '/');
        }
        $path = ltrim($path, '/');

        $base = $scriptDir !== '' ? $scriptDir : '';

        // Fallback: si SCRIPT_NAME ne donne pas le préfixe (certaines conf Apache),
        // on déduit le 1er segment depuis REQUEST_URI.
        if ($base === '') {
            $reqPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
            $segments = array_values(array_filter(explode('/', trim($reqPath, '/'))));
            if (count($segments) >= 1) {
                $base = '/' . (string) $segments[0];
            }
        }

        if ($base === '') {
            $appUrl = Config::env('APP_URL', '');
            if (is_string($appUrl) && $appUrl !== '') {
                $appPath = parse_url($appUrl, PHP_URL_PATH) ?: '';
                $appPath = rtrim((string) $appPath, '/');
                if ($appPath !== '') {
                    $base = $appPath;
                }
            }
        }

        $target = ($base !== '' ? $base : '') . '/' . $path;

        // Cas limite: si base est vide, on veut "/foo".
        if ($base === '') {
            $target = '/' . $path;
        }

        $headers = ['Location' => $target];

        // En dev: expose le calcul pour debug rapide du basePath.
        try {
            if (Config::isDebug()) {
                $headers['X-Pilora-Debug-Redirect-Base'] = (string) $base;
                $headers['X-Pilora-Debug-Redirect-Path'] = (string) $path;
                $headers['X-Pilora-Debug-Redirect-Target'] = (string) $target;
            }
        } catch (\Throwable) {
            // ignore
        }

        return new self('', $status, $headers);
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE), $status, $headers);
    }
}

