import { createAbortController, debounce, request } from '../core/http.js';
import { setButtonLoading, setLoadingState, toast } from '../core/ui.js';

function cleanFilters(form) {
  const data = new FormData(form);
  const output = {};
  for (const [key, value] of data.entries()) {
    const text = String(value).trim();
    if (!text) continue;
    if (key === 'salary_min' || key === 'salary_max') {
      const num = Number(text);
      if (Number.isFinite(num) && num >= 0) output[key] = String(num);
      continue;
    }
    output[key] = text;
  }
  if (output.salary_min && output.salary_max && Number(output.salary_min) > Number(output.salary_max)) {
    [output.salary_min, output.salary_max] = [output.salary_max, output.salary_min];
  }
  return output;
}

function renderCard(job, savedIds = []) {
  const article = document.createElement('article');
  article.className = 'block border-b border-slate-200 bg-white p-5 transition hover:bg-slate-50';
  article.dataset.jobCard = '1';
  article.dataset.jobId = String(job.id);

  const isSaved = savedIds.includes(Number(job.id));
  article.innerHTML = `<div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-[#c64a44]">Nueva oportunidad</p><h3 class="mt-2 text-lg font-bold text-[#25364b]"></h3><p class="mt-1 text-sm font-medium text-slate-600"></p></div><span class="text-lg ${isSaved ? 'text-[#17457c]' : 'text-slate-300'}">${isSaved ? '♥' : '♡'}</span></div><p class="mt-3 text-sm text-slate-600"></p><div class="mt-4 flex items-center justify-between text-xs text-slate-500"><span></span><span></span></div>`;
  article.querySelector('h3').textContent = job.title || '';
  article.querySelectorAll('p')[1].textContent = job.company_name || '';
  article.querySelectorAll('p')[2].textContent = `${job.city || ''} · ${job.work_mode || ''}`;
  article.querySelectorAll('span')[1].textContent = job.experience || '';
  article.querySelectorAll('span')[2].textContent = (job.created_at || '').slice(0, 10);
  return article;
}

function bindJobSelection(root, jobs) {
  const detail = root.querySelector('#job-detail');
  if (!detail) return;
  const title = detail.querySelector('[data-job-title]');
  const company = detail.querySelector('[data-job-company]');
  const location = detail.querySelector('[data-job-location]');
  const description = detail.querySelector('[data-job-description]');
  const apply = detail.querySelector('[data-apply-job]');
  const save = detail.querySelector('[data-save-job]');
  const hide = detail.querySelector('[data-hide-job]');

  root.querySelectorAll('[data-job-card]').forEach((card) => {
    card.addEventListener('click', () => {
      const job = jobs.find((item) => Number(item.id) === Number(card.dataset.jobId));
      if (!job) return;
      if (title) title.textContent = job.title || '';
      if (company) company.textContent = job.company_name || '';
      if (location) location.textContent = job.city || '';
      if (description) description.textContent = job.description || '';
      if (apply) apply.dataset.applyJob = String(job.id);
      if (save) save.dataset.saveJob = String(job.id);
      if (hide) hide.dataset.hideJob = String(job.id);
    });
  });
}

export function initSearchModule(root = document) {
  const section = root.querySelector('[data-modules*="search"]');
  const form = section?.querySelector('#candidate-search-form');
  const list = section?.querySelector('#search-results');
  const loadMore = section?.querySelector('#search-load-more');
  if (!section || !form || !list) return;

  let page = 1;
  let hasMore = true;
  let lastFilters = cleanFilters(form);
  let controller = createAbortController();

  const load = async (append = false) => {
    if (!hasMore && append) return;
    if (!append) page = 1;
    controller.abort();
    controller = createAbortController();
    setLoadingState(list, 'loading');

    try {
      const result = await request('search_jobs', {
        params: { ...lastFilters, page, per_page: 10 },
        signal: controller.signal
      });
      const items = result.data.items || [];
      hasMore = Boolean(result.data.has_more);
      if (!append) list.innerHTML = '';
      items.forEach((job) => list.appendChild(renderCard(job, result.data.saved_ids || [])));
      const total = section.querySelector('[data-search-total]');
      if (total) total.textContent = String(result.data.total || items.length);
      bindJobSelection(section, items);
      if (loadMore) loadMore.disabled = !hasMore;
      setLoadingState(list, 'idle');
    } catch (error) {
      if (error.name === 'AbortError') return;
      setLoadingState(list, 'error', error.message);
      toast(error.message, 'error');
    }
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    setButtonLoading(button, true);
    lastFilters = cleanFilters(form);
    await load(false);
    setButtonLoading(button, false);
  });

  form.addEventListener('input', debounce(async () => {
    lastFilters = cleanFilters(form);
    await load(false);
  }, 350));

  loadMore?.addEventListener('click', async () => {
    page += 1;
    await load(true);
  });

  section.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const saveButton = target.closest('[data-save-job]');
    const hideButton = target.closest('[data-hide-job]');
    if (!saveButton && !hideButton) return;

    const action = saveButton ? 'toggle_saved_job' : 'hide_job';
    const jobId = Number((saveButton || hideButton).dataset.saveJob || (saveButton || hideButton).dataset.hideJob);
    if (!jobId) return;

    try {
      await request(action, { method: 'POST', body: { job_id: jobId } });
      toast(saveButton ? 'Favorito actualizado.' : 'Vacante ocultada.', 'success');
      await load(false);
    } catch (error) {
      toast(error.message, 'error');
    }
  });

  load(false);
}
