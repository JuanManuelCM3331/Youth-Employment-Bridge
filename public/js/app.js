import { initAdminDashboardModule } from './modules/admin-dashboard.js';
import { initApplicationsModule } from './modules/applications.js';
import { initCompaniesModule } from './modules/companies.js';
import { initJobsModule } from './modules/jobs.js';
import { initSearchModule } from './modules/search.js';

const root = document;

const moduleRegistry = {
  search: initSearchModule,
  jobs: initJobsModule,
  applications: initApplicationsModule,
  companies: initCompaniesModule,
  'admin-dashboard': initAdminDashboardModule,
};

const requested = new Set();
root.querySelectorAll('[data-modules]').forEach((el) => {
  const names = (el.getAttribute('data-modules') || '').split(/\s+/).map((name) => name.trim()).filter(Boolean);
  names.forEach((name) => requested.add(name));
});

requested.forEach((name) => {
  const initializer = moduleRegistry[name];
  if (typeof initializer === 'function') initializer(root);
});

if (!requested.size) {
  Object.values(moduleRegistry).forEach((initializer) => initializer(root));
}
