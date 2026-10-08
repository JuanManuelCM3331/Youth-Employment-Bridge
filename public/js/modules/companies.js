import { request } from '../core/http.js';
import { setButtonLoading, toast } from '../core/ui.js';

export function initCompaniesModule(root = document) {
  const form = root.querySelector('#company-profile-form');
  if (!form) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    setButtonLoading(button, true);
    try {
      await request('save_profile', { method: 'POST', body: new FormData(form) });
      toast('Perfil corporativo actualizado.', 'success');
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setButtonLoading(button, false);
    }
  });
}
