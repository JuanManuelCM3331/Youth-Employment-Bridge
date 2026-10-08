import { request } from '../core/http.js';
import { setButtonLoading, toast } from '../core/ui.js';

export function initApplicationsModule(root = document) {
  const applyButtons = root.querySelectorAll('[data-apply-job]');
  applyButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      setButtonLoading(button, true);
      try {
        await request('apply', { method: 'POST', body: { job_id: button.dataset.applyJob } });
        toast('Postulación enviada correctamente.', 'success');
      } catch (error) {
        toast(error.message, 'error');
      } finally {
        setButtonLoading(button, false);
      }
    });
  });

  root.querySelectorAll('.js-application-status-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button');
      setButtonLoading(button, true);
      try {
        await request('update_application', { method: 'POST', body: new FormData(form) });
        toast('Estado actualizado.', 'success');
      } catch (error) {
        toast(error.message, 'error');
      } finally {
        setButtonLoading(button, false);
      }
    });
  });
}
