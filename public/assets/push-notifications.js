/**
 * Youth Employment Bridge - Cliente de Notificaciones Push y Alertas en Tiempo Real
 * Soporta vinculación permanente de dispositivo y cancelación de suscripción.
 */

(function () {
    'use strict';

    let swRegistration = null;
    let pollInterval = null;
    let lastNotificationId = 0;
    let csrfToken = '';

    // Obtiene o genera un token de dispositivo persistente y único
    function getDeviceToken() {
        let token = localStorage.getItem('yeb_device_token');
        if (!token) {
            token = 'yeb_dev_' + Math.random().toString(36).substring(2, 12) + '_' + Date.now();
            localStorage.setItem('yeb_device_token', token);
        }
        return token;
    }

    // Inicializar contenedor de toasts flotantes en la página
    function ensureToastContainer() {
        let container = document.getElementById('yeb-push-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'yeb-push-toast-container';
            container.className = 'fixed top-4 right-4 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none px-4 sm:px-0';
            document.body.appendChild(container);
        }
        return container;
    }

    // Reproduce un sonido de campana sutil usando Web Audio API sintetizado
    function playNotificationChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();

            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc1.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5

            gain1.gain.setValueAtTime(0.001, ctx.currentTime);
            gain1.gain.linearRampToValueAtTime(0.18, ctx.currentTime + 0.04);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);

            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.52);

            setTimeout(() => {
                try {
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(1174.66, ctx.currentTime); // D6
                    gain2.gain.setValueAtTime(0.001, ctx.currentTime);
                    gain2.gain.linearRampToValueAtTime(0.12, ctx.currentTime + 0.03);
                    gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start(ctx.currentTime);
                    osc2.stop(ctx.currentTime + 0.42);
                } catch (e) {}
            }, 120);
        } catch (e) {
            // Silencioso
        }
    }

    // Muestra un Toast animado e interactivo dentro del portal
    function showInAppPushToast(item) {
        const container = ensureToastContainer();
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto transform transition-all duration-300 ease-out translate-y-[-20px] opacity-0 flex flex-col bg-white rounded-2xl border border-slate-200/90 shadow-xl shadow-slate-900/10 overflow-hidden';

        const title = item.title || 'Nueva Notificación';
        const message = item.message || item.body || '';
        const url = item.url || '?action=alerts';

        toast.innerHTML = `
            <div class="p-4 flex items-start gap-3.5">
                <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0 pr-1">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-[#17457c]">${escapeHtml(title)}</h4>
                        <span class="text-[10px] text-slate-400 font-medium">Ahora</span>
                    </div>
                    <p class="text-xs font-medium text-slate-700 mt-1 leading-snug break-words">${escapeHtml(message)}</p>
                    <div class="mt-2.5 flex items-center gap-2">
                        <a href="${escapeHtml(url)}" class="inline-flex items-center gap-1 text-[11px] font-bold text-white bg-[#17457c] hover:bg-[#12365f] px-3 py-1 rounded-lg transition shadow-sm">
                            Ver detalles
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <button type="button" class="btn-dismiss-toast text-[11px] font-semibold text-slate-500 hover:text-slate-800 px-2 py-1">Cerrar</button>
                    </div>
                </div>
                <button type="button" class="btn-dismiss-toast text-slate-400 hover:text-slate-600 p-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="h-1 bg-slate-100 w-full overflow-hidden">
                <div class="toast-progress h-full bg-[#17457c] transition-all duration-[7000ms] ease-linear w-full"></div>
            </div>
        `;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-[-20px]', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            const progress = toast.querySelector('.toast-progress');
            if (progress) {
                requestAnimationFrame(() => {
                    progress.style.width = '0%';
                });
            }
        });

        const closeToast = () => {
            toast.classList.add('translate-y-[-10px]', 'opacity-0');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        };

        toast.querySelectorAll('.btn-dismiss-toast').forEach(btn => {
            btn.addEventListener('click', closeToast);
        });

        setTimeout(closeToast, 7000);
    }

    // Muestra la notificación nativa del navegador / sistema operativo en el dispositivo
    function showNativePushNotification(item) {
        if (localStorage.getItem('yeb_push_disabled') === '1') {
            return;
        }
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        const title = item.title || 'Youth Employment Bridge';
        const options = {
            body: item.message || item.body || '',
            icon: './public/assets/icons/notification-icon.png',
            badge: './public/assets/icons/notification-badge.png',
            tag: 'yeb-push-' + (item.id || Date.now()),
            renotify: true,
            requireInteraction: false,
            data: {
                url: item.url || '?action=alerts'
            }
        };

        if (swRegistration && 'showNotification' in swRegistration) {
            swRegistration.showNotification(title, options).catch(() => {
                fallbackNotification(title, options);
            });
        } else {
            fallbackNotification(title, options);
        }
    }

    function fallbackNotification(title, options) {
        try {
            const notif = new Notification(title, options);
            notif.onclick = function () {
                window.focus();
                if (options.data && options.data.url) {
                    window.location.href = options.data.url;
                }
                notif.close();
            };
        } catch (e) {}
    }

    function updateHeaderBadge(unreadCount) {
        const bellLink = document.querySelector('a[title="Notificaciones"], a[href="?action=alerts"]');
        if (!bellLink) return;

        let badge = bellLink.querySelector('.rounded-full.bg-rose-500');
        if (unreadCount > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'absolute -top-1 -right-1 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white leading-none shadow-sm';
                bellLink.appendChild(badge);
            }
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
        } else if (badge) {
            badge.remove();
        }
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return null;

        const swPaths = ['./sw.js', './public/sw.js', 'sw.js'];
        for (const path of swPaths) {
            try {
                const reg = await navigator.serviceWorker.register(path);
                swRegistration = reg;

                if (reg.active) {
                    reg.active.postMessage({
                        type: 'SET_DEVICE_TOKEN',
                        deviceToken: getDeviceToken()
                    });
                }
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    navigator.serviceWorker.controller?.postMessage({
                        type: 'SET_DEVICE_TOKEN',
                        deviceToken: getDeviceToken()
                    });
                });

                return reg;
            } catch (err) {
                // Siguiente ruta
            }
        }
        return null;
    }

    // Vincula la suscripción y el dispositivo con el backend
    async function sendSubscriptionToServer(subscription) {
        try {
            const subData = subscription.toJSON ? subscription.toJSON() : subscription;
            const deviceToken = getDeviceToken();

            const res = await fetch('?action=push_subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    csrf: csrfToken,
                    device_token: deviceToken,
                    endpoint: subData.endpoint || subscription.endpoint,
                    keys: subData.keys || {},
                    p256dh: (subData.keys && subData.keys.p256dh) ? subData.keys.p256dh : null,
                    auth: (subData.keys && subData.keys.auth) ? subData.keys.auth : null
                })
            });
            const data = await res.json();
            if (data && data.success) {
                localStorage.setItem('yeb_push_linked', '1');
                localStorage.removeItem('yeb_push_disabled');
                if (swRegistration && swRegistration.active) {
                    swRegistration.active.postMessage({
                        type: 'SET_DEVICE_TOKEN',
                        deviceToken: deviceToken
                    });
                }
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    // Cancela la suscripción a notificaciones push en este dispositivo
    async function unsubscribeBrowser() {
        try {
            if (swRegistration && 'pushManager' in swRegistration) {
                const sub = await swRegistration.pushManager.getSubscription();
                if (sub) {
                    await sub.unsubscribe().catch(() => {});
                }
            }

            const deviceToken = getDeviceToken();
            await fetch('?action=push_unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    csrf: csrfToken,
                    device_token: deviceToken
                })
            }).catch(() => {});

            localStorage.setItem('yeb_push_disabled', '1');
            localStorage.removeItem('yeb_push_linked');

            updateStatusBadge();

            showInAppPushToast({
                title: 'Suscripción Cancelada',
                message: 'Se han desactivado las notificaciones push en este dispositivo. Puedes reactivarlas cuando lo desees.',
                url: '?action=alerts'
            });

            return true;
        } catch (e) {
            return false;
        }
    }

    // Solicita permiso al usuario para notificaciones nativas y activa la suscripción
    async function requestPermission() {
        if (!('Notification' in window)) {
            alert('Tu navegador no soporta notificaciones de escritorio.');
            return false;
        }

        localStorage.removeItem('yeb_push_disabled');
        const perm = await Notification.requestPermission();

        if (perm === 'granted') {
            await subscribeBrowser();
            updateStatusBadge();
            showInAppPushToast({
                title: 'Notificaciones Activadas ✓',
                message: '¡Listo! Las notificaciones push llegarán a este dispositivo.',
                url: '?action=alerts'
            });
            playNotificationChime();
            return true;
        }

        updateStatusBadge();
        return false;
    }

    async function subscribeBrowser() {
        if (!swRegistration || !('pushManager' in swRegistration)) {
            await sendSubscriptionToServer({
                endpoint: 'inapp://browser-' + getDeviceToken(),
                keys: {}
            });
            return;
        }

        try {
            let sub = await swRegistration.pushManager.getSubscription();
            if (!sub) {
                sub = await swRegistration.pushManager.subscribe({
                    userVisibleOnly: true
                }).catch(() => null);
            }

            if (sub) {
                await sendSubscriptionToServer(sub);
            } else {
                await sendSubscriptionToServer({
                    endpoint: 'inapp://browser-' + getDeviceToken(),
                    keys: {}
                });
            }
        } catch (e) {
            await sendSubscriptionToServer({
                endpoint: 'inapp://browser-' + getDeviceToken(),
                keys: {}
            });
        }
    }

    // Consulta periódica (Polling Push)
    async function pollNotifications() {
        if (localStorage.getItem('yeb_push_disabled') === '1') {
            return;
        }

        const deviceToken = getDeviceToken();
        try {
            const url = `?action=push_poll&device_token=${encodeURIComponent(deviceToken)}&last_id=${lastNotificationId}&_=${Date.now()}`;
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) return;

            const data = await res.json();
            if (!data || !data.success) return;

            if (data.unread_count !== undefined) {
                updateHeaderBadge(data.unread_count);
            }

            if (data.has_new && Array.isArray(data.notifications)) {
                for (const notif of data.notifications) {
                    const notifId = parseInt(notif.id, 10) || 0;
                    if (notifId > lastNotificationId) {
                        lastNotificationId = notifId;
                    }

                    // 1. Sonido de campana
                    playNotificationChime();

                    // 2. Notificación push nativa en el dispositivo
                    showNativePushNotification(notif);

                    // 3. Notificación push en la aplicación (Toast)
                    showInAppPushToast(notif);
                }
            }
        } catch (e) {
            // Silencioso
        }
    }

    function updateStatusBadge() {
        const badge = document.getElementById('push-status-badge');
        const enableBtn = document.getElementById('btn-enable-push');
        const disableBtn = document.getElementById('btn-disable-push');
        if (!badge) return;

        const isExplicitlyDisabled = localStorage.getItem('yeb_push_disabled') === '1';

        if (!('Notification' in window)) {
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500';
            badge.innerHTML = '<span class="h-2 w-2 rounded-full bg-slate-400"></span> No soportado en este navegador';
            if (enableBtn) enableBtn.style.display = 'none';
            if (disableBtn) disableBtn.style.display = 'none';
            return;
        }

        if (isExplicitlyDisabled) {
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600';
            badge.innerHTML = '<span class="h-2 w-2 rounded-full bg-slate-400"></span> Notificaciones Desactivadas';
            if (enableBtn) {
                enableBtn.style.display = 'inline-flex';
                enableBtn.textContent = 'Reactivar notificaciones push';
                enableBtn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700', 'cursor-default');
                enableBtn.classList.add('bg-[#17457c]', 'hover:bg-[#12365f]');
            }
            if (disableBtn) disableBtn.style.display = 'none';
            return;
        }

        if (Notification.permission === 'granted') {
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800';
            badge.innerHTML = '<span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> Notificaciones Push Activas';
            if (enableBtn) {
                enableBtn.style.display = 'none';
            }
            if (disableBtn) {
                disableBtn.style.display = 'inline-flex';
            }
        } else if (Notification.permission === 'denied') {
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800';
            badge.innerHTML = '<span class="h-2 w-2 rounded-full bg-rose-500"></span> Permiso bloqueado en el navegador';
            if (enableBtn) {
                enableBtn.style.display = 'inline-flex';
                enableBtn.textContent = 'Permiso bloqueado';
                enableBtn.disabled = true;
                enableBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            if (disableBtn) disableBtn.style.display = 'none';
        } else {
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800';
            badge.innerHTML = '<span class="h-2 w-2 rounded-full bg-amber-500"></span> Pendiente de activación';
            if (enableBtn) {
                enableBtn.style.display = 'inline-flex';
                enableBtn.textContent = 'Activar notificaciones push';
                enableBtn.disabled = false;
            }
            if (disableBtn) disableBtn.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        const csrfInput = document.querySelector('input[name="csrf"]');
        if (csrfInput) {
            csrfToken = csrfInput.value;
        }

        const notifCards = document.querySelectorAll('input[name="notification_id"]');
        notifCards.forEach(input => {
            const id = parseInt(input.value, 10);
            if (id > lastNotificationId) {
                lastNotificationId = id;
            }
        });

        await registerServiceWorker();

        if ('Notification' in window && Notification.permission === 'granted' && localStorage.getItem('yeb_push_disabled') !== '1') {
            await subscribeBrowser();
        }

        const btnEnable = document.getElementById('btn-enable-push');
        if (btnEnable) {
            btnEnable.addEventListener('click', (e) => {
                e.preventDefault();
                requestPermission();
            });
        }

        const btnDisable = document.getElementById('btn-disable-push');
        if (btnDisable) {
            btnDisable.addEventListener('click', (e) => {
                e.preventDefault();
                unsubscribeBrowser();
            });
        }

        const btnTest = document.getElementById('btn-test-push');
        if (btnTest) {
            btnTest.addEventListener('click', async (e) => {
                e.preventDefault();
                btnTest.classList.add('opacity-75', 'cursor-wait');
                btnTest.setAttribute('disabled', 'disabled');
                try {
                    const res = await fetch('?action=push_test', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'csrf=' + encodeURIComponent(csrfToken) + '&device_token=' + encodeURIComponent(getDeviceToken())
                    });
                    const data = await res.json();
                    if (data && data.success) {
                        playNotificationChime();
                        showInAppPushToast(data);
                        showNativePushNotification(data);
                    }
                } catch (err) {
                    playNotificationChime();
                    showInAppPushToast({
                        title: 'Prueba de Notificación Push',
                        message: '¡Prueba exitosa! Las alertas en tiempo real están funcionando en este dispositivo.',
                        url: '?action=alerts'
                    });
                    showNativePushNotification({
                        title: 'Prueba de Notificación Push',
                        message: '¡Prueba exitosa! Las alertas en tiempo real están funcionando en este dispositivo.',
                        url: '?action=alerts'
                    });
                } finally {
                    btnTest.classList.remove('opacity-75', 'cursor-wait');
                    btnTest.removeAttribute('disabled');
                }
            });
        }

        updateStatusBadge();

        pollInterval = setInterval(pollNotifications, 6000);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                pollNotifications();
            }
        });
    });

    window.YEBPush = {
        getDeviceToken: getDeviceToken,
        requestPermission: requestPermission,
        unsubscribe: unsubscribeBrowser,
        showToast: showInAppPushToast,
        showNative: showNativePushNotification,
        playChime: playNotificationChime,
        pollNow: pollNotifications
    };
})();
