const appRoot = document.getElementById('app-root');
const apiBase = appRoot?.dataset.apiBase || './?ajax=1&format=json';
const csrf = document.body?.dataset.csrf || '';

function buildUrl(action, params = {}) {
  const url = new URL(apiBase, window.location.href);
  url.searchParams.set('action', action);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });
  return url.toString();
}

export async function request(action, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const headers = new Headers(options.headers || {});
  let body = options.body;

  if (body && !(body instanceof FormData) && typeof body === 'object') {
    const form = new URLSearchParams();
    Object.entries(body).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        form.set(key, String(value));
      }
    });
    body = form;
    headers.set('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
  }

  if (method !== 'GET') {
    if (body instanceof FormData) {
      if (!body.has('csrf') && csrf) body.set('csrf', csrf);
    } else if (body instanceof URLSearchParams) {
      if (!body.get('csrf') && csrf) body.set('csrf', csrf);
    }
  }

  const response = await fetch(buildUrl(action, options.params), {
    method,
    headers,
    body: method === 'GET' ? undefined : body,
    signal: options.signal,
    credentials: 'same-origin'
  });

  let payload = null;
  try {
    payload = await response.json();
  } catch (_error) {
    payload = { ok: false, message: 'Respuesta inválida del servidor.', data: {}, errors: {} };
  }

  if (!response.ok || !payload.ok) {
    const message = payload?.message || 'No se pudo completar la operación.';
    const error = new Error(message);
    error.payload = payload;
    throw error;
  }

  return payload;
}

export function debounce(fn, wait = 300) {
  let timeoutId;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn(...args), wait);
  };
}

export function createAbortController() {
  return new AbortController();
}
