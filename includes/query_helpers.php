<?php
function hrQueryRows(mysqli $mysqli, string $sql, string $types = '', array $params = []): array
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new RuntimeException('Cannot prepare database query');
    try {
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new RuntimeException('Cannot execute database query');
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally { $stmt->close(); }
}

function hrRememberDataset(array &$cache, string $key, callable $load) {
    if (!array_key_exists($key, $cache)) $cache[$key] = $load();
    return $cache[$key];
}
