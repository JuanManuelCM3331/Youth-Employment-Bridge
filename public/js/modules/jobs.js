import { request } from '../core/http.js';
import { confirmAction, setButtonLoading, toast } from '../core/ui.js';

function fillForm(form, payload) {
  Object.entries(payload).forEach(([key, value]) => {
    const field = form.elements.namedItem(key);
    if (field) field.value = value ?? '';
  });
  const idField = form.querySelector('[data-job-id-field]');
  if (idField) idField.value = String(payload.id || '');
}

export function initJobsModule(root = document) {
  const section = root.querySelector('[data-modules*="jobs"]');
  const form = section?.querySelector('#job-create-form');
  const list = section?.querySelector('#company-jobs-list');
  if (!section || !form || !list) return;

  section.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const edit = target.closest('[data-job-edit]');
    if (edit) {
      const row = section.querySelector(`[data-job-id="${edit.dataset.jobEdit}"]`);
      const payload = row?.dataset.jobPayload ? JSON.parse(row.dataset.jobPayload) : null;
      if (payload) fillForm(form, payload);
      return;
    }

    if (target.closest('[data-job-reset]')) {
      form.reset();
      const idField = form.querySelector('[data-job-id-field]');
      if (idField) idField.value = '';
      return;
    }

    const statusButton = target.closest('[data-job-toggle-status]');
    if (statusButton) {
      const ok = await confirmAction('¿Deseas pausar esta vacante?');
      if (!ok) return;
      try {
        await request('update_job_status', { method: 'POST', body: { job_id: statusButton.dataset.jobToggleStatus, status: statusButton.dataset.statusTarget || 'closed' } });
        toast('Estado actualizado.', 'success');
        window.location.reload();
      } catch (error) {
        toast(error.message, 'error');
      }
      return;
    }

    const deleteButton = target.closest('[data-job-delete]');
    if (deleteButton) {
      const ok = await confirmAction('Esta acción eliminará la vacante. ¿Continuar?');
      if (!ok) return;
      try {
        await request('delete_job', { method: 'POST', body: { job_id: deleteButton.dataset.jobDelete } });
        toast('Vacante eliminada.', 'success');
        window.location.reload();
      } catch (error) {
        toast(error.message, 'error');
      }
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('[data-job-submit]');
    setButtonLoading(button, true);
    const formData = new FormData(form);
    const jobId = formData.get('job_id');
    const action = jobId ? 'update_job' : 'create_job';
    try {
      await request(action, { method: 'POST', body: formData });
      toast(jobId ? 'Vacante actualizada.' : 'Vacante creada.', 'success');
      window.location.reload();
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setButtonLoading(button, false);
    }
  });
}
