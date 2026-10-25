const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
const define = (action, fallback, intensity, priority, holdMs) => Object.freeze({
  action,
  fallback,
  intensity,
  priority,
  holdMs,
});

// Emotional families already performed by the v12 engine (emotion-performances.js).
// Declaring them here is what makes them reachable: normalizeIntent refuses unknown kinds.
export const EMOTION_DEFINITIONS = Object.freeze({
  shy: define('shy', 'IDLE', .50, 60, 4200),
  proud: define('proud', 'CONGRATULATE', .70, 60, 4200),
  sad: define('sad', 'IDLE', .50, 60, 4200),
  laughing: define('laughing', 'CONGRATULATE', .80, 60, 3800),
  grateful: define('grateful', 'IDLE', .60, 60, 4200),
  relieved: define('relieved', 'IDLE', .55, 60, 4000),
  embarrassed: define('embarrassed', 'IDLE', .50, 60, 4000),
  confused: define('confused', 'THINKING', .60, 60, 4200),
  worried: define('worried', 'ERROR', .65, 60, 4200),
  tired: define('tired', 'IDLE', .50, 60, 4200),
});

// Childlike gestures already animated by the v12 engine (base-controller actions).
export const GESTURE_DEFINITIONS = Object.freeze({
  wave: define('wave', 'IDLE', .50, 55, 2800),
  thumbsUp: define('thumbsUp', 'CONGRATULATE', .75, 55, 3000),
  clap: define('clap', 'CONGRATULATE', .80, 55, 2600),
  jump: define('jump', 'CONGRATULATE', .80, 55, 2000),
  yes: define('yes', 'IDLE', .60, 55, 2600),
  no: define('no', 'IDLE', .60, 55, 3200),
  guide: define('guide', 'SPEAKING', .70, 55, 4200),
  pointLeft: define('pointLeft', 'SPEAKING', .70, 55, 3400),
  pointRight: define('pointRight', 'SPEAKING', .70, 55, 3400),
  reassure: define('reassure', 'IDLE', .50, 55, 3600),
  curtsy: define('curtsy', 'IDLE', .50, 55, 3000),
  noseScratch: define('noseScratch', 'IDLE', .40, 55, 3400),
  sendStanding: define('sendStanding', 'SPEAKING', .60, 55, 3600),
  sendKneeling: define('sendKneeling', 'SPEAKING', .60, 55, 4200),
  insight: define('insight', 'THINKING', .70, 55, 4200),
  surpriseGesture: define('surpriseGesture', 'THINKING', .75, 55, 3600),
  balance: define('balance', 'IDLE', .50, 55, 3600),
  fidget: define('fidget', 'IDLE', .35, 55, 3400),
  hips: define('hips', 'IDLE', .45, 55, 3000),
  footTap: define('footTap', 'IDLE', .30, 55, 3200),
  heelPlay: define('heelPlay', 'IDLE', .35, 55, 3400),
  toePlay: define('toePlay', 'IDLE', .35, 55, 3400),
  sideStep: define('sideStep', 'IDLE', .40, 55, 3000),
  wink: define('wink', 'IDLE', .45, 55, 1600),
});

export const INTENT_DEFINITIONS = Object.freeze({
  idle: define('idle', 'IDLE', .35, 0, 0),
  listen: define('attentive', 'LISTENING', .55, 20, 4000),
  think: define('deepThink', 'THINKING', .65, 30, 6000),
  speak: define('guide', 'SPEAKING', .60, 40, 4500),
  explain: define('guide', 'SPEAKING', .75, 40, 4500),
  reassure: define('attentive', 'IDLE', .45, 70, 4200),
  success: define('thumbsUp', 'CONGRATULATE', .80, 70, 3600),
  refuse: define('worried', 'ERROR', .65, 100, 4200),
  error: define('worried', 'ERROR', .75, 100, 4200),
  ...EMOTION_DEFINITIONS,
  ...GESTURE_DEFINITIONS,
});

export function normalizeIntent(input) {
  if (!input || typeof input !== 'object' || !Object.hasOwn(INTENT_DEFINITIONS, input.kind)) return null;
  const definition = INTENT_DEFINITIONS[input.kind];
  const intensity = Number.isFinite(input.intensity) ? clamp(input.intensity, 0, 1) : definition.intensity;
  const holdMs = Number.isFinite(input.holdMs) ? clamp(input.holdMs, 0, 15000) : definition.holdMs;
  return {
    kind: input.kind,
    action: definition.action,
    fallback: definition.fallback,
    intensity,
    holdMs,
    priority: definition.priority,
    source: String(input.source || 'application'),
  };
}

const SURFACES = new Set(['auth', 'workspace-bust', 'workspace-avatar']);

export function createPresentationController({
  createPrimary,
  createFallback,
  setTimer = setTimeout,
  clearTimer = clearTimeout,
} = {}) {
  let mounted = false;
  let destroyed = false;
  let primary = null;
  let fallback = null;
  let timer = 0;
  let sequence = 0;
  let current = normalizeIntent({ kind: 'idle' });
  let queued = null;

  const snapshot = () => ({
    mounted,
    destroyed,
    current: { ...current },
    queued: queued ? { ...queued } : null,
    engine: primary ? 'v12' : fallback ? 'webp' : 'none',
  });
  const cancelTimer = () => {
    if (timer) {
      clearTimer(timer);
      timer = 0;
    }
  };
  const scheduleIdle = intent => {
    cancelTimer();
    if (!intent.holdMs || intent.kind === 'idle') return;
    timer = setTimer(() => {
      timer = 0;
      finish(intent.sequence);
    }, intent.holdMs);
  };
  const render = intent => {
    const command = {
      action: intent.action,
      fallback: intent.fallback,
      intensity: intent.intensity,
      source: intent.source,
    };
    fallback?.play(command);
    const result = primary?.play(command);
    const abandonPrimary = () => {
      if (destroyed || current.sequence !== intent.sequence) return;
      primary?.destroy?.();
      primary = null;
      fallback?.play(command);
    };
    if (result === false) abandonPrimary();
    else if (result && typeof result.then === 'function') {
      result.then(ok => {
        if (ok === false) abandonPrimary();
      }).catch(abandonPrimary);
    }
    scheduleIdle(intent);
  };
  const finish = id => {
    if (destroyed || current.sequence !== id) return;
    const next = queued;
    queued = null;
    current = next || { ...normalizeIntent({ kind: 'idle' }), sequence: ++sequence };
    render(current);
  };

  return {
    async mount({ host, surface, isVisible = () => true } = {}) {
      if (destroyed || mounted || !host || !SURFACES.has(surface) || typeof isVisible !== 'function') return false;
      mounted = true;
      fallback = createFallback?.({ host, surface, isVisible }) || null;
      current = { ...current, sequence: ++sequence };
      render(current);
      try {
        primary = await createPrimary?.({ host, surface, isVisible }) || null;
      } catch {
        primary = null;
      }
      if (destroyed) {
        primary?.destroy?.();
        primary = null;
        return false;
      }
      if (primary) render(current);
      return true;
    },
    dispatch(input) {
      if (!mounted || destroyed) return false;
      const normalized = normalizeIntent(input);
      if (!normalized) return false;
      if (normalized.kind === current.kind && normalized.intensity === current.intensity
          && normalized.holdMs === current.holdMs) return true;
      const next = { ...normalized, sequence: ++sequence };
      if (input.replace !== true && current.kind !== 'idle' && next.priority < current.priority) {
        if (next.kind !== 'idle' && (!queued || next.priority >= queued.priority)) queued = next;
        return false;
      }
      if (input.replace === true) queued = null;
      current = next;
      render(current);
      return true;
    },
    stop(reason = 'stop') {
      if (!mounted || destroyed) return false;
      cancelTimer();
      queued = null;
      primary?.stop?.(reason);
      fallback?.stop?.(reason);
      current = { ...normalizeIntent({ kind: 'idle', source: reason }), sequence: ++sequence };
      render(current);
      return true;
    },
    destroy() {
      if (destroyed) return false;
      destroyed = true;
      mounted = false;
      cancelTimer();
      queued = null;
      primary?.destroy?.();
      fallback?.destroy?.();
      primary = null;
      fallback = null;
      return true;
    },
    getState: snapshot,
  };
}
