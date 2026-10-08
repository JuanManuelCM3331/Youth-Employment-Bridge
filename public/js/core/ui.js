export function toast(message, type = 'success') {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const item = document.createElement('div');
  item.className = `toast ${type === 'error' ? 'error' : 'success'}`;
  item.textContent = message;
  container.appendChild(item);
  setTimeout(() => item.remove(), 3200);
}

export function setButtonLoading(button, isLoading) {
  if (!button) return;
  if (isLoading) {
    button.dataset.originalText = button.textContent || '';
    button.disabled = true;
    button.textContent = button.dataset.loadingText || 'Procesando...';
    return;
  }
  button.disabled = false;
  if (button.dataset.originalText) {
    button.textContent = button.dataset.originalText;
  }
}

export function setLoadingState(target, state = 'idle', message = '') {
  if (!target) return;
  if (state === 'loading') {
    target.dataset.state = 'loading';
    target.style.opacity = '0.6';
  } else {
    target.dataset.state = state;
    target.style.opacity = '1';
  }
  if (state === 'error' && message) {
    target.dataset.error = message;
  }
}

export function confirmAction(message) {
  const modal = document.getElementById('confirm-modal');
  if (!modal) return Promise.resolve(window.confirm(message));
  const msg = modal.querySelector('#confirm-message');
  const cancel = modal.querySelector('[data-confirm-cancel]');
  const accept = modal.querySelector('[data-confirm-accept]');
  if (msg) msg.textContent = message;
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');

  return new Promise((resolve) => {
    const close = (answer) => {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      cancel?.removeEventListener('click', onCancel);
      accept?.removeEventListener('click', onAccept);
      resolve(answer);
    };
    const onCancel = () => close(false);
    const onAccept = () => close(true);
    cancel?.addEventListener('click', onCancel);
    accept?.addEventListener('click', onAccept);
  });
}
