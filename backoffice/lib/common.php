<?php
// SARAH common compatibility layer.
// This file keeps the new library browser/editor helpers aligned with the existing
// authenticated backoffice (login + 2FA + roles).

require_once __DIR__ . '/bootstrap.php';

// Viewing the library is allowed to all authenticated roles.
// Mutating APIs/pages still use their own stricter require_role() calls.
require_role(['superadmin', 'editor', 'viewer']);

if (!function_exists('sarah_storage_root')) {
    function sarah_storage_root(): string {
        return storage_root();
    }
}

if (!function_exists('sarah_seed_path')) {
    function sarah_seed_path(): string {
        return seed_path();
    }
}

if (!function_exists('sarah_icons_root')) {
    function sarah_icons_root(): string {
        $root = sarah_storage_root() . DIRECTORY_SEPARATOR . 'icons';
        if (!is_dir($root)) mkdir($root, 0775, true);
        return realpath($root) ?: $root;
    }
}

if (!function_exists('sarah_normalize_relative_path')) {
    function sarah_normalize_relative_path(string $path): string {
        return normalize_relative_path($path);
    }
}

if (!function_exists('sarah_safe_storage_path')) {
    function sarah_safe_storage_path(string $relative): string {
        return safe_storage_path($relative);
    }
}

if (!function_exists('sarah_seed_default')) {
    function sarah_seed_default(): array {
        return [
            'versao' => 1,
            'idioma' => 'pt-PT',
            'voz' => ['lang' => 'pt-PT', 'rate' => 1.0, 'pitch' => 1.0],
            'categorias' => [
                ['id' => 'base', 'nome' => 'Base', 'emoji' => '🏁'],
                ['id' => 'importados', 'nome' => 'Importados', 'emoji' => '📦']
            ],
            'itens' => []
        ];
    }
}

if (!function_exists('sarah_seed_is_valid')) {
    function sarah_seed_is_valid(array $seed): bool {
        return seed_is_valid_structure($seed);
    }
}

if (!function_exists('sarah_read_seed')) {
    function sarah_read_seed(): array {
        $path = sarah_seed_path();
        if (!file_exists($path)) {
            $seed = sarah_seed_default();
            sarah_write_seed($seed);
            return $seed;
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || !sarah_seed_is_valid($data)) {
            return sarah_seed_default();
        }
        return $data;
    }
}

if (!function_exists('sarah_write_seed')) {
    function sarah_write_seed(array $seed): bool {
        return file_put_contents(
            sarah_seed_path(),
            json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }
}

if (!function_exists('sarah_svg_sanitize')) {
    function sarah_svg_sanitize(string $svg): ?string {
        if (function_exists('sanitize_svg_content')) {
            return sanitize_svg_content($svg);
        }
        if (stripos($svg, '<svg') === false) return null;
        $patterns = [
            '/<script\b[^>]*>.*?<\/script>/is',
            '/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is',
            '/javascript\s*:/is',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/<object\b[^>]*>.*?<\/object>/is',
            '/<embed\b[^>]*>.*?<\/embed>/is',
            '/<foreignObject\b[^>]*>.*?<\/foreignObject>/is',
        ];
        foreach ($patterns as $p) $svg = preg_replace($p, '', $svg);
        return stripos($svg, '<svg') !== false ? trim($svg) : null;
    }
}

if (!function_exists('sarah_list_all_svg')) {
    function sarah_list_all_svg(): array {
        $items = [];
        $root = sarah_storage_root();
        $iconsRoot = sarah_icons_root();

        if (!is_dir($iconsRoot)) return $items;

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($iconsRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            if (strtolower($file->getExtension()) !== 'svg') continue;
            if ($file->getSize() > 1024 * 1024) continue;

            $full = $file->getPathname();
            $rel = str_replace('\\', '/', substr($full, strlen(rtrim($root, '/\\')) + 1));
            $content = file_get_contents($full);
            if ($content === false) continue;

            $parts = explode('/', $rel);
            $category = (count($parts) >= 2 && $parts[0] === 'icons') ? $parts[1] : 'geral';

            $items[] = [
                'path' => $rel,
                'fileName' => basename($rel),
                'name' => preg_replace('/\.svg$/i', '', basename($rel)),
                'category' => $category,
                'tags' => [],
                'content' => $content,
            ];
        }

        usort($items, fn($a, $b) => strcasecmp($a['path'], $b['path']));
        return $items;
    }
}
