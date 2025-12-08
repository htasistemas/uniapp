<?php
declare(strict_types=1);

/**
 * Retorna o limite por segundo aplicado aos endpoints públicos (cores e logos).
 */
function get_public_rate_limit(): int
{
    static $limit = null;
    if ($limit !== null) {
        return $limit;
    }

    $raw = PluginUniappConfig::get('public_colors_rps', '300');
    $value = (int)$raw;
    if ($value <= 0) {
        $value = 300;
    }

    $limit = $value;
    return $limit;
}

/**
 * Enforce a simple fixed-window rate limit writing counts to a temp file.
 *
 * @param int $limit
 * @param float $windowSeconds
 * @param string $storageFilename
 */
function enforce_public_rate_limit(int $limit, float $windowSeconds = 1.0, string $storageFilename = 'uniapp-public-rate.json'): void
{
    $storage = sys_get_temp_dir() . '/' . $storageFilename;
    $windowKey = (int)floor(microtime(true) / $windowSeconds);
    $state = ['window' => $windowKey, 'count' => 0];

    $handle = @fopen($storage, 'c+b');
    if (!$handle) {
        return;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return;
    }

    $content = stream_get_contents($handle);
    if ($content !== '') {
        $existing = json_decode($content, true);
        if (is_array($existing) && isset($existing['window'], $existing['count'])) {
            if ((int)$existing['window'] === $windowKey) {
                $state['count'] = max(0, (int)$existing['count']);
            }
        }
    }

    if ($state['count'] >= $limit) {
        flock($handle, LOCK_UN);
        fclose($handle);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error'   => 'Limite temporario atingido'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $state['count']++;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}
