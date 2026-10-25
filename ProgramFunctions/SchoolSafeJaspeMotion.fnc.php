<?php
/**
 * SchoolSafe JASPE - choix déterministe de l'émotion et du geste (2026-10-17).
 *
 * Le moteur v12 anime déjà ces familles et ces gestes ; sans ce signal l'interface ne
 * pouvait atteindre que six états de conversation. Les identifiants retournés doivent
 * exister dans presentation-controller.js (EMOTION_DEFINITIONS / GESTURE_DEFINITIONS).
 *
 * @param array $result Réponse de SchoolSafeJaspeConversation() ou du repli Cloudflare.
 * @return array{0:?string,1:?string} [émotion|null, geste|null]
 */
function SchoolSafeJaspeMotionRules(array $result): array
{
    $state = strtoupper((string) ($result['state'] ?? ''));
    $reply = mb_strtolower((string) ($result['reply'] ?? ''), 'UTF-8');
    $navigate = $result['navigate'] ?? null;
    if (in_array($state, ['ERROR', 'OFFLINE'], true)) return ['worried', null];
    if (strpos($reply, 'pas compris') !== false || strpos($reply, 'je ne sais pas') !== false) return ['confused', null];
    if (strpos($reply, 'emoji') !== false) return [null, 'sendStanding'];
    // Les réponses arrivent en français accentué : les motifs tolèrent les accents.
    if (preg_match('/(f[ée]licit|bravo|r[ée]ussi|enregistr|cr[ée][ée])/u', $reply)) return ['proud', 'thumbsUp'];
    if (preg_match('/(merci|reconnaiss)/u', $reply)) return ['grateful', null];
    if (preg_match('/(attention|erreur|probl[èe]me|danger)/u', $reply)) return ['worried', null];
    if (is_string($navigate) && $navigate !== '') return [null, 'guide'];
    if (preg_match('/^bonjour\b/u', $reply)) return [null, 'wave'];
    return [null, null];
}
