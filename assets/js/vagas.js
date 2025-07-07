// vagas.js 

// Utilitário para obter a URL base da API
function getApiBaseUrl() {
  return 'api';
}

let allJobs = [];
let filteredJobs = [];

// Modal de criar/editar vaga
let jobModal = null;
let editingJobId = null;

// Buscar empresas para o select
let empresasCache = [];

// Carregar vagas ao iniciar
window.addEventListener('DOMContentLoaded', () => {
  fetchJobs();
  document.getElementById('jobSearchInput').addEventListener('input', applyFilters);
  document.querySelectorAll('.status-checkbox').forEach(cb => cb.addEventListener('change', applyFilters));
  document.getElementById('createJobButton').addEventListener('click', openCreateJobModal);
});

async function fetchJobs() {
  setJobListLoading(true);
  try {
    const resp = await fetch(`${getApiBaseUrl()}/get_jobs.php`);
    const data = await resp.json();
    allJobs = Array.isArray(data) ? data.map(job => ({
      ...job,
      company: job.company || job.empresa_nome || ''
    })) : [];
    applyFilters();
  } catch (e) {
    showJobListError('Erro ao carregar vagas: ' + e.message);
  } finally {
    setJobListLoading(false);
  }
}

function applyFilters() {
  const search = document.getElementById('jobSearchInput').value.toLowerCase();
  const status = Array.from(document.querySelectorAll('.status-checkbox:checked')).map(cb => cb.value);
  filteredJobs = allJobs.filter(job => {
    const matchText = job.title.toLowerCase().includes(search) || job.company.toLowerCase().includes(search);
    const matchStatus = status.includes(job.status);
    return matchText && matchStatus;
  });
  renderJobList();
}

function renderJobList() {
  const list = document.getElementById('jobPostingsList');
  const stats = document.getElementById('jobFilterStats');
  if (!filteredJobs.length) {
    list.innerHTML = `<div class="bg-gray-50 rounded-xl p-8 text-center">
      <div class="text-gray-400 text-5xl mb-4"><i class="fas fa-search"></i></div>
      <p class="text-gray-600 text-lg mb-2">Nenhuma vaga encontrada</p>
      <p class="text-gray-500">Tente ajustar os filtros para ver mais resultados</p>
    </div>`;
    stats.textContent = 'Mostrando 0 vagas';
    return;
  }
  stats.textContent = `Mostrando ${filteredJobs.length} vaga${filteredJobs.length > 1 ? 's' : ''}`;
  list.innerHTML = filteredJobs.map(job => `
    <div class="bg-white rounded-xl shadow p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border border-gray-100">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-lg font-semibold text-navy">${job.title}</span>
          <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(job.status)}">${job.status}</span>
        </div>
        <div class="text-sm text-gray-600">${job.company}</div>
        <div class="text-xs text-gray-500 mt-1">Prazo: ${job.deadline ? new Date(job.deadline).toLocaleDateString('pt-BR') : '-'}</div>
      </div>
      <div class="flex flex-wrap gap-2 w-full md:w-auto">
        <button class="view-job-details bg-accent text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 transition flex items-center gap-2" data-id="${job.id}"><i class="fas fa-eye"></i> Detalhes</button>
        <button class="edit-job bg-yellow-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-yellow-700 transition flex items-center gap-2" data-id="${job.id}"><i class="fas fa-edit"></i> Editar</button>
        <button class="delete-job bg-red-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-700 transition flex items-center gap-2" data-id="${job.id}"><i class="fas fa-trash"></i> Excluir</button>
        <button class="view-candidates bg-secondary text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-800 transition flex items-center gap-2" data-id="${job.id}"><i class="fas fa-users"></i> Candidatos</button>
      </div>
    </div>
  `).join('');
  // Eventos dos botões
  list.querySelectorAll('.view-job-details').forEach(btn => btn.onclick = () => openJobDetails(btn.dataset.id));
  list.querySelectorAll('.edit-job').forEach(btn => btn.onclick = () => openEditJobModal(btn.dataset.id));
  list.querySelectorAll('.delete-job').forEach(btn => btn.onclick = () => deleteJob(btn.dataset.id));
  list.querySelectorAll('.view-candidates').forEach(btn => btn.onclick = () => openCandidatesModal(btn.dataset.id));
}

function getStatusClass(status) {
  switch (status) {
    case 'Em Divulgação': return 'bg-yellow-100 text-yellow-800';
    case 'Suspensa': return 'bg-red-100 text-red-800';
    case 'Aguardando Retorno': return 'bg-gray-100 text-gray-800';
    case 'Fechada': return 'bg-green-100 text-green-800';
    default: return 'bg-gray-100 text-gray-800';
  }
}

function setJobListLoading(loading) {
  const list = document.getElementById('jobPostingsList');
  if (loading) {
    list.innerHTML = `<div class='text-center py-8'><i class='fas fa-spinner fa-spin fa-2x text-accent'></i><div class='mt-2 text-accent font-medium'>Carregando vagas...</div></div>`;
  }
}

function showJobListError(msg) {
  const list = document.getElementById('jobPostingsList');
  list.innerHTML = `<div class='text-center py-8 text-red-600 font-medium'>${msg}</div>`;
}

function openCreateJobModal() {
  editingJobId = null;
  showJobModal({});
}

function openEditJobModal(id) {
  const job = allJobs.find(j => j.id == id);
  if (!job) return alert('Vaga não encontrada!');
  editingJobId = id;
  showJobModal(job);
}

// Utilitário para animação de entrada
function animateModal(modalBox) {
  modalBox.style.opacity = '0';
  modalBox.style.transform = 'translateY(40px) scale(0.98)';
  setTimeout(() => {
    modalBox.style.transition = 'all .22s cubic-bezier(.4,0,.2,1)';
    modalBox.style.opacity = '1';
    modalBox.style.transform = 'translateY(0) scale(1)';
  }, 10);
}

async function showJobModal(job) {
  if (!jobModal) {
    jobModal = document.createElement('div');
    jobModal.id = 'jobModal';
    jobModal.style.position = 'fixed';
    jobModal.style.top = '0';
    jobModal.style.left = '0';
    jobModal.style.width = '100vw';
    jobModal.style.height = '100vh';
    jobModal.style.background = 'rgba(0,0,0,0.38)';
    jobModal.style.display = 'flex';
    jobModal.style.alignItems = 'center';
    jobModal.style.justifyContent = 'center';
    jobModal.style.zIndex = '9999';
    document.body.appendChild(jobModal);
  }
  // Buscar empresas
  const empresas = await fetchEmpresas();
  // Buscar candidatos inscritos se for edição
  let candidatosInscritos = [];
  if (job.id) candidatosInscritos = await fetchCandidatosInscritos(job.id);
  // Montar select de empresas
  const empresaOptions = empresas.map(emp => `<option value="${emp.id}" ${job.empresa_id == emp.id ? 'selected' : ''}>${emp.nome}</option>`).join('');
  // Montar select de candidatos
  const candidatoOptions = candidatosInscritos.length ? candidatosInscritos.map(c => `<option value="${c.candidato_email}" ${job.candidato == c.candidato_email ? 'selected' : ''}>${c.candidato_nome} (${c.candidato_email})</option>`).join('') : '<option value="">Nenhum inscrito</option>';
  jobModal.innerHTML = `
    <div class="modal-box" style="background:#fff;max-width:600px;width:98vw;height:600px;display:flex;flex-direction:column;box-shadow:0 8px 32px #2563eb33,0 2px 12px #0002;border-radius:20px;position:relative;overflow:hidden;">
      <div style="background:#2563eb;color:#fff;padding:18px 32px 12px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2;box-shadow:0 2px 8px #2563eb22;">
        <h2 style="font-size:1.2rem;font-weight:600;margin:0;">${editingJobId ? 'Editar Vaga' : 'Nova Vaga'}</h2>
        <button id="closeJobModal" style="background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:38px;height:38px;font-size:24px;cursor:pointer;color:#fff;transition:background 0.2s;">&times;</button>
      </div>
      <div style="flex:1;overflow-y:auto;padding:0 0 12px 0;">
        <form id="jobForm" style="padding:24px 32px;display:grid;grid-template-columns:1fr 1fr;gap:18px;">
          <div style="grid-column:1/3;">
            <label class="block text-sm font-medium mb-1 text-gray-700">Empresa</label>
            <input type="text" name="company" placeholder="Nome da empresa" value="${job.company || ''}" required class="border border-gray-300 rounded-lg px-4 py-2 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Título</label>
            <input type="text" name="title" placeholder="Título da Vaga" value="${job.title || ''}" required class="border border-gray-300 rounded-lg px-4 py-2 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Quantidade</label>
            <input type="number" name="quantidade" min="1" value="${job.quantidade || 1}" required class="border border-gray-300 rounded-lg px-4 py-2 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Localização</label>
            <input type="text" name="location" placeholder="Localização" value="${job.location || ''}" class="border border-gray-300 rounded-lg px-4 py-2 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Faixa Salarial</label>
            <input type="text" name="salary_range" placeholder="Faixa Salarial" value="${job.salary_range || ''}" class="border border-gray-300 rounded-lg px-4 py-2 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Prazo de Inscrição</label>
            <input type="date" name="deadline" value="${job.deadline ? job.deadline.split('T')[0] : ''}" required class="border border-gray-300 rounded-lg px-4 py-2 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Status</label>
            <select name="status" required class="border border-gray-300 rounded-lg px-4 py-2 w-full">
              <option value="Em Divulgação" ${job.status === 'Em Divulgação' ? 'selected' : ''}>Em Divulgação</option>
              <option value="Suspensa" ${job.status === 'Suspensa' ? 'selected' : ''}>Suspensa</option>
              <option value="Aguardando Retorno" ${job.status === 'Aguardando Retorno' ? 'selected' : ''}>Aguardando Retorno</option>
              <option value="Fechada" ${job.status === 'Fechada' ? 'selected' : ''}>Fechada</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Status Registro</label>
            <select name="status_registro" required class="border border-gray-300 rounded-lg px-4 py-2 w-full">
              <option value="ativo" ${job.status_registro === 'ativo' ? 'selected' : ''}>Ativo</option>
              <option value="inativo" ${job.status_registro === 'inativo' ? 'selected' : ''}>Inativo</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Candidato Selecionado</label>
            <select name="candidato" class="border border-gray-300 rounded-lg px-4 py-2 w-full">
              <option value="">Nenhum</option>
              ${candidatoOptions}
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Escolaridade</label>
            <select name="escolaridade" class="border border-gray-300 rounded-lg px-4 py-2 w-full">
              <option value="">Não exigida</option>
              <option value="Fundamental" ${job.escolaridade === 'Fundamental' ? 'selected' : ''}>Fundamental</option>
              <option value="Médio" ${job.escolaridade === 'Médio' ? 'selected' : ''}>Médio</option>
              <option value="Superior" ${job.escolaridade === 'Superior' ? 'selected' : ''}>Superior</option>
              <option value="Pós" ${job.escolaridade === 'Pós' ? 'selected' : ''}>Pós-graduação</option>
            </select>
          </div>
          <div style="grid-column:1/3;">
            <label class="block text-sm font-medium mb-1 text-gray-700">Descrição</label>
            <textarea name="description" placeholder="Descrição" rows="2" class="border border-gray-300 rounded-lg px-4 py-2 w-full">${job.description || ''}</textarea>
          </div>
          <div style="grid-column:1/3;">
            <label class="block text-sm font-medium mb-1 text-gray-700">Requisitos</label>
            <textarea name="requirements" placeholder="Requisitos" rows="2" class="border border-gray-300 rounded-lg px-4 py-2 w-full">${job.requirements || ''}</textarea>
          </div>
          <div style="grid-column:1/3;">
            <label class="block text-sm font-medium mb-1 text-gray-700">Palavras-chave</label>
            <input type="text" name="keywords" placeholder="Palavras-chave" value="${job.keywords || ''}" class="border border-gray-300 rounded-lg px-4 py-2 w-full" />
          </div>
          <button type="submit" class="bg-accent text-white py-2.5 px-6 rounded-lg font-medium hover:bg-blue-700 transition mt-2 col-span-2">${editingJobId ? 'Salvar Alterações' : 'Criar Vaga'}</button>
        </form>
      </div>
    </div>
  `;
  const modalBox = jobModal.querySelector('.modal-box');
  animateModal(modalBox);
  jobModal.onclick = function(e) {
    if (e.target === jobModal || e.target.id === 'closeJobModal') {
      jobModal.remove();
      jobModal = null;
    }
  };
  document.getElementById('jobForm').onsubmit = submitJobForm;
}

async function submitJobForm(e) {
  e.preventDefault();
  const form = e.target;
  const data = Object.fromEntries(new FormData(form));
  data.deadline = data.deadline || null;
  let url = `${getApiBaseUrl()}/save_job.php`;
  let method = 'POST';
  if (editingJobId) {
    data.id = editingJobId;
  } else {
    // Ao criar, garantir que não exista o campo id
    if ('id' in data) delete data.id;
  }
  form.querySelector('button[type="submit"]').disabled = true;
  form.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
  try {
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await resp.json();
    if (result.error) throw new Error(result.message || 'Erro ao salvar vaga');
    alert(editingJobId ? 'Vaga atualizada com sucesso!' : 'Vaga criada com sucesso!');
    jobModal.remove();
    fetchJobs();
  } catch (err) {
    alert('Erro: ' + err.message);
  } finally {
    form.querySelector('button[type="submit"]').disabled = false;
    form.querySelector('button[type="submit"]').innerHTML = editingJobId ? 'Salvar Alterações' : 'Criar Vaga';
  }
}

// Exclusão real de vaga
async function deleteJob(id) {
  if (!confirm('Deseja realmente excluir esta vaga?')) return;
  try {
    const resp = await fetch(`${getApiBaseUrl()}/manage_jobs.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id })
    });
    const result = await resp.json();
    if (result.error) throw new Error(result.message || 'Erro ao excluir vaga');
    alert('Vaga excluída com sucesso!');
    fetchJobs();
  } catch (err) {
    alert('Erro ao excluir vaga: ' + err.message);
  }
}

// Modal de detalhes da vaga
function openJobDetails(id) {
  const job = allJobs.find(j => j.id == id);
  if (!job) return alert('Vaga não encontrada!');
  let modal = document.getElementById('jobDetailsModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'jobDetailsModal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(0,0,0,0.38)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';
    document.body.appendChild(modal);
  }
  modal.innerHTML = `
    <div class="modal-box" style="background:#fff;max-width:600px;width:98vw;height:520px;display:flex;flex-direction:column;box-shadow:0 8px 32px #2563eb33,0 2px 12px #0002;border-radius:20px;position:relative;overflow:hidden;">
      <div style="background:#2563eb;color:#fff;padding:18px 32px 12px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2;box-shadow:0 2px 8px #2563eb22;">
        <h2 style="font-size:1.2rem;font-weight:600;margin:0;">Detalhes da Vaga</h2>
        <button id="closeJobDetailsModal" style="background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:38px;height:38px;font-size:24px;cursor:pointer;color:#fff;transition:background 0.2s;">&times;</button>
      </div>
      <div style="flex:1;overflow-y:auto;padding:24px 32px;">
        <p><b>Título:</b> ${job.title || '-'}</p>
        <p><b>Empresa:</b> ${job.company || '-'}</p>
        <p><b>Localização:</b> ${job.location || '-'}</p>
        <p><b>Faixa Salarial:</b> ${job.salary_range || '-'}</p>
        <p><b>Status:</b> ${job.status || '-'}</p>
        <p><b>Prazo de Inscrição:</b> ${job.deadline ? new Date(job.deadline).toLocaleDateString('pt-BR') : '-'}</p>
        <p><b>Descrição:</b> ${job.description || '-'}</p>
        <p><b>Requisitos:</b> ${job.requirements || '-'}</p>
        <p><b>Palavras-chave:</b> ${job.keywords || '-'}</p>
      </div>
    </div>
  `;
  const modalBox = modal.querySelector('.modal-box');
  animateModal(modalBox);
  modal.onclick = function(e) {
    if (e.target === modal || e.target.id === 'closeJobDetailsModal') modal.remove();
  };
}

// Modal de candidatos da vaga
async function openCandidatesModal(id) {
  let modal = document.getElementById('candidatesModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'candidatesModal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(0,0,0,0.38)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';
    document.body.appendChild(modal);
  }
  modal.innerHTML = `<div class="modal-box" style="background:#fff;max-width:900px;width:98vw;height:600px;display:flex;flex-direction:column;box-shadow:0 8px 32px #2563eb33,0 2px 12px #0002;border-radius:20px;position:relative;overflow:hidden;">
    <div style="background:#2563eb;color:#fff;padding:18px 32px 12px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2;box-shadow:0 2px 8px #2563eb22;">
      <h2 style="font-size:1.2rem;font-weight:600;margin:0;">Candidatos da Vaga</h2>
      <button id="closeCandidatesModal" style="background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:38px;height:38px;font-size:24px;cursor:pointer;color:#fff;transition:background 0.2s;">&times;</button>
    </div>
    <div id="candidatesContent" style="flex:1;overflow-y:auto;padding:24px 32px;min-height:120px;">Carregando candidatos...</div>
  </div>`;
  const modalBox = modal.querySelector('.modal-box');
  animateModal(modalBox);
  modal.onclick = function(e) {
    if (e.target === modal || e.target.id === 'closeCandidatesModal') modal.remove();
  };
  // Buscar candidatos da vaga
  try {
    const resp = await fetch(`${getApiBaseUrl()}/get_inscritos.php?vaga_id=${id}`);
    const data = await resp.json();
    renderCandidatesTable(data, document.getElementById('candidatesContent'));
  } catch (err) {
    document.getElementById('candidatesContent').innerHTML = `<div class='text-red-600 font-medium'>Erro ao carregar candidatos: ${err.message}</div>`;
  }
}

function renderCandidatesTable(candidatos, container) {
  if (!Array.isArray(candidatos) || !candidatos.length) {
    container.innerHTML = `<div class='text-gray-600'>Nenhum candidato inscrito para esta vaga.</div>`;
    return;
  }
  container.innerHTML = `<div class='overflow-x-auto'><table class='min-w-full divide-y divide-gray-200 text-sm'><thead class='bg-gray-100'><tr><th class='px-4 py-2 text-left font-medium text-gray-600 uppercase tracking-wider'>Candidato</th><th class='px-4 py-2 text-left font-medium text-gray-600 uppercase tracking-wider'>E-mail</th><th class='px-4 py-2 text-left font-medium text-gray-600 uppercase tracking-wider'>Status</th><th class='px-4 py-2 text-left font-medium text-gray-600 uppercase tracking-wider'>Currículo</th><th class='px-4 py-2'></th></tr></thead><tbody class='bg-white divide-y divide-gray-200'></tbody></table></div>`;
  const tbody = container.querySelector('tbody');
  candidatos.forEach(c => {
    const row = document.createElement('tr');
    row.innerHTML = `<td class='px-4 py-2'>${c.candidato_nome || '-'}</td><td class='px-4 py-2'>${c.candidato_email || '-'}</td><td class='px-4 py-2'>${c.status_selecao || '-'}</td><td class='px-4 py-2'>${c.curriculo_link ? `<a href='${c.curriculo_link}' target='_blank' class='text-blue-600 hover:underline'>Ver Currículo</a>` : 'N/D'}</td><td class='px-4 py-2'><button class='detalhes-candidato bg-accent text-white px-3 py-1 rounded hover:bg-blue-700 transition' data-candidato='${encodeURIComponent(JSON.stringify(c))}'>Detalhes</button></td>`;
    tbody.appendChild(row);
  });
  tbody.querySelectorAll('.detalhes-candidato').forEach(btn => {
    btn.onclick = function() {
      const cand = JSON.parse(decodeURIComponent(this.dataset.candidato));
      showCandidatoDetalhesModal(cand);
    };
  });
}

function showCandidatoDetalhesModal(c) {
  let modal = document.getElementById('candidatoDetalhesModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'candidatoDetalhesModal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(0,0,0,0.38)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';
    document.body.appendChild(modal);
  }
  modal.innerHTML = `<div class='modal-box' style='background:#fff;max-width:480px;width:98vw;height:auto;display:flex;flex-direction:column;box-shadow:0 8px 32px #2563eb33,0 2px 12px #0002;border-radius:20px;position:relative;overflow:hidden;'>
    <div style='background:#2563eb;color:#fff;padding:18px 32px 12px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2;box-shadow:0 2px 8px #2563eb22;'>
      <h2 style='font-size:1.1rem;font-weight:600;margin:0;'>Detalhes do Candidato</h2>
      <button id='closeCandidatoDetalhesModal' style='background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:38px;height:38px;font-size:24px;cursor:pointer;color:#fff;transition:background 0.2s;'>&times;</button>
    </div>
    <div style='flex:1;overflow-y:auto;padding:24px 32px;'>
      <p><b>Nome:</b> ${c.candidato_nome || '-'}</p>
      <p><b>E-mail:</b> ${c.candidato_email || '-'}</p>
      <p><b>Status Seleção:</b> ${c.status_selecao || '-'}</p>
      <p><b>Data Inscrição:</b> ${c.data_inscricao ? new Date(c.data_inscricao).toLocaleString('pt-BR') : '-'}</p>
      <p><b>Data Entrevista:</b> ${c.data_entrevista ? new Date(c.data_entrevista).toLocaleString('pt-BR') : '-'}</p>
      <p><b>Observações Entrevista:</b> ${c.observacoes_entrevista || '-'}</p>
      <p><b>Currículo:</b> ${c.curriculo_link ? `<a href='${c.curriculo_link}' target='_blank' class='text-blue-600 hover:underline'>Baixar Currículo</a>` : 'N/D'}</p>
    </div>
  </div>`;
  const modalBox = modal.querySelector('.modal-box');
  animateModal(modalBox);
  modal.onclick = function(e) {
    if (e.target === modal || e.target.id === 'closeCandidatoDetalhesModal') modal.remove();
  };
}

// Filtro visual (minimizar/expandir)
window.toggleFilters = function() {
  const content = document.getElementById('filtersContent');
  const icon = document.getElementById('filterToggleIcon');
  if (content.style.display === 'none') {
    content.style.display = '';
    icon.className = 'fas fa-chevron-up';
  } else {
    content.style.display = 'none';
    icon.className = 'fas fa-chevron-down';
  }
};

// Buscar empresas para o select
async function fetchEmpresas() {
  if (empresasCache.length) return empresasCache;
  try {
    const resp = await fetch(`${getApiBaseUrl()}/get_empresas.php`);
    const data = await resp.json();
    empresasCache = Array.isArray(data) ? data : [];
    return empresasCache;
  } catch (e) {
    alert('Erro ao buscar empresas: ' + e.message);
    return [];
  }
}

// Buscar candidatos inscritos para o select de candidato selecionado
async function fetchCandidatosInscritos(vagaId) {
  try {
    const resp = await fetch(`${getApiBaseUrl()}/get_inscritos.php?vaga_id=${vagaId}`);
    const data = await resp.json();
    return Array.isArray(data) ? data : [];
  } catch (e) {
    return [];
  }
} 