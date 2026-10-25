<?php
declare(strict_types=1);

/** Validate already signature-verified ID claims and an explicit Rosario link. */
function schoolSafeKeycloakIdentity(array $claims, array $transaction, array $config, int $now): array
{
    if (($claims['iss'] ?? null) !== $config['issuer']) {
        throw new RuntimeException('Invalid issuer');
    }
    $audience = $claims['aud'] ?? [];
    $audience = is_string($audience) ? [$audience] : $audience;
    if (!is_array($audience) || !in_array($config['client_id'], $audience, true)
        || (count($audience) > 1 && ($claims['azp'] ?? null) !== $config['client_id'])
        || (isset($claims['azp']) && $claims['azp'] !== $config['client_id'])) {
        throw new RuntimeException('Invalid audience');
    }
    if (!is_int($claims['exp'] ?? null) || $claims['exp'] <= $now
        || !is_int($claims['iat'] ?? null) || $claims['iat'] > $now + 30
        || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > $now + 30))) {
        throw new RuntimeException('Invalid token lifetime');
    }
    if (!is_string($claims['nonce'] ?? null) || !is_string($transaction['nonce'] ?? null)
        || !hash_equals($transaction['nonce'], $claims['nonce'])
        || !is_int($transaction['created'] ?? null) || $transaction['created'] > $now
        || $transaction['created'] < $now - 300) {
        throw new RuntimeException('Invalid authentication transaction');
    }
    $subject = $claims['sub'] ?? null;
    if (!is_string($subject) || $subject === '' || !isset($config['links'][$subject])) {
        throw new RuntimeException('Identity is not explicitly linked');
    }
    return $config['links'][$subject];
}

function schoolSafeKeycloakAdmin(array $row, array $link): bool
{
    return ($row['PROFILE'] ?? null) === 'admin'
        && (string) ($row['STAFF_ID'] ?? '') === (string) $link['staff_id']
        && ($row['USERNAME'] ?? null) === $link['username'];
}
