<?php

function recordAuditEvent(
    PDO $pdo,
    int $actorId,
    string $action,
    string $entityType,
    int $entityId,
    string $details = ''
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$actorId, $action, $entityType, $entityId, $details]);
}
