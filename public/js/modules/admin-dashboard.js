import { request } from '../core/http.js';
import { confirmAction, toast } from '../core/ui.js';

function renderCompanies(container, items) {
  if (!container) return;
  if (!items.length) {
    container.innerHTML = '<p class="text-slate-500">No hay empresas para moderar.</p>';
    return;
  }

  container.innerHTML = '';
  items.forEach((company) => {
    const card = document.createElement('div');
    card.className = 'mb-3 rounded-xl border p-3';
    const status = Number(company.verified) === 1;
    card.innerHTML = `<p class="font-semibold"></p><p class="text-xs text-slate-500"></p><div class="mt-2 flex items-center justify-between"><span class="text-xs ${status ? 'text-emerald-700' : 'text-amber-700'}">${status ? 'Verificada' : 'Pendiente'}</span><button type="button" class="filter-pill" data-company-verify="${company.id}" data-next="${status ? 0 : 1}">${status ? 'Marcar pendiente' : 'Aprobar'}</button></div>`;
    card.querySelector('p').textContent = company.name || '';
    card.querySelectorAll('p')[1].textContent = `${company.city || ''} · ${company.industry || 'Sin sector'}`;
    container.appendChild(card);
  });
}

function renderUsers(container, items) {
  if (!container) return;
  if (!items.length) {
    container.innerHTML = '<p class="text-slate-500">No hay usuarios.</p>';
    return;
  }

  container.innerHTML = '';
  items.forEach((user) => {
    const row = document.createElement('div');
    row.className = 'mb-2 rounded-lg border p-2 text-sm';
    row.innerHTML = `<strong></strong><p class="text-xs text-slate-500"></p><button type="button" class="filter-pill mt-2" disabled title="Sin soporte de suspensión en el esquema">Suspender (no disponible)</button>`;
    row.querySelector('strong').textContent = user.name || '';
    row.querySelector('p').textContent = `${user.email || ''} · ${user.role || ''}`;
    container.appendChild(row);
  });
}

function renderAuditRows(tbody, items, append = false) {
  if (!tbody) return;
  if (!append) tbody.innerHTML = '';
  items.forEach((log) => {
    const tr = document.createElement('tr');
    tr.className = 'border-t';
    [log.created_at, log.user_name || 'Sistema', log.action, log.module, log.description, log.ip_address].forEach((value) => {
      const td = document.createElement('td');
      td.className = 'px-3 py-3';
      td.textContent = value || '';
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
}

export function initAdminDashboardModule(root = document) {
  const section = root.querySelector('[data-modules*="admin-dashboard"]');
  if (!section) return;

  const companiesContainer = section.querySelector('#admin-companies-list');
  const usersContainer = section.querySelector('#admin-users-list');
  const auditRows = section.querySelector('#audit-log-rows');
  const auditForm = section.querySelector('#audit-filters');
  const loadMore = section.querySelector('#audit-load-more');
  let auditPage = 1;
  let auditHasMore = true;

  const loadCompanies = async () => {
    const result = await request('companies_moderation', { params: { limit: 12, offset: 0 } });
    renderCompanies(companiesContainer, result.data.items || []);
  };

  const loadUsers = async () => {
    const result = await request('users_moderation', { params: { limit: 12, offset: 0 } });
    renderUsers(usersContainer, result.data.items || []);
  };

  const loadAudit = async (append = false) => {
    const fields = new FormData(auditForm || undefined);
    const params = {
      page: auditPage,
      per_page: 20,
      module: (fields.get('module') || '').toString().trim(),
      status: (fields.get('status') || '').toString().trim()
    };
    const result = await request('audit_logs', { params });
    renderAuditRows(auditRows, result.data.items || [], append);
    auditHasMore = Boolean(result.data.has_more);
    if (loadMore) loadMore.disabled = !auditHasMore;
  };

  section.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.matches('[data-admin-refresh-companies]')) {
      try { await loadCompanies(); toast('Listado de empresas actualizado.', 'success'); } catch (error) { toast(error.message, 'error'); }
      return;
    }
    if (target.matches('[data-admin-refresh-users]')) {
      try { await loadUsers(); toast('Listado de usuarios actualizado.', 'success'); } catch (error) { toast(error.message, 'error'); }
      return;
    }

    const verify = target.closest('[data-company-verify]');
    if (verify) {
      const next = Number(verify.dataset.next || '0');
      const ok = await confirmAction(next === 1 ? '¿Aprobar esta empresa?' : '¿Marcar esta empresa como pendiente?');
      if (!ok) return;
      try {
        await request('set_company_verification', { method: 'POST', body: { company_id: verify.dataset.companyVerify, verified: next } });
        toast('Estado de empresa actualizado.', 'success');
        await loadCompanies();
      } catch (error) {
        toast(error.message, 'error');
      }
      return;
    }

    if (target === loadMore) {
      if (!auditHasMore) return;
      auditPage += 1;
      try { await loadAudit(true); } catch (error) { toast(error.message, 'error'); }
    }
  });

  auditForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    auditPage = 1;
    try {
      await loadAudit(false);
      toast('Auditoría actualizada.', 'success');
    } catch (error) {
      toast(error.message, 'error');
    }
  });

  Promise.all([loadCompanies(), loadUsers(), loadAudit(false)]).catch((error) => toast(error.message, 'error'));
}
