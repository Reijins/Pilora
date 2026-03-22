<?php
declare(strict_types=1);

namespace Modules\Platform\Repositories;

final class PackRepository
{
    private function filePath(): string
    {
        $root = dirname(__DIR__, 3);
        return $root . '/storage/settings/platform_packs.json';
    }

    public function listAll(): array
    {
        $path = $this->filePath();
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $decoded = json_decode(is_string($raw) ? $raw : '[]', true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        foreach ($this->listAll() as $p) {
            if ((int) ($p['id'] ?? 0) === $id) {
                return $p;
            }
        }

        return null;
    }

    public function upsert(array $pack): void
    {
        $packs = $this->listAll();
        $id = (int) ($pack['id'] ?? 0);
        if ($id <= 0) {
            $max = 0;
            foreach ($packs as $p) {
                $max = max($max, (int) ($p['id'] ?? 0));
            }
            $id = $max + 1;
            $pack['id'] = $id;
            $packs[] = $pack;
        } else {
            $updated = false;
            foreach ($packs as $k => $p) {
                if ((int) ($p['id'] ?? 0) === $id) {
                    $packs[$k] = $pack;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $packs[] = $pack;
            }
        }

        $path = $this->filePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode(array_values($packs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function delete(int $id): void
    {
        $packs = $this->listAll();
        $packs = array_values(array_filter($packs, static fn (array $p): bool => (int) ($p['id'] ?? 0) !== $id));
        $path = $this->filePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($packs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

