// curriculos.js 

function getApiBaseUrl() {
  return window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    ? '/public_html/dash/api'
    : 'api';
}

let resumes = [];

async function loadAreas() {
  try {
    const response = await fetch(`${getApiBaseUrl()}/get_areas.php`);
    if (!response.ok) throw new Error('Erro HTTP: ' + response.status);
    const areas = await response.json();
    const areaFilter = document.getElementById('areaFilter');
    areaFilter.innerHTML = '<option value="">Todas as áreas</option>';
    areas.forEach(area => {
      if (area && area.trim()) {
        const option = document.createElement('option');
        option.value = area;
        option.textContent = area;
        areaFilter.appendChild(option);
      }
    });
  } catch (error) {
    console.error('Erro ao carregar áreas:', error);
  }
}

async function fetchResumes() {
  try {
    const resumeTableBody = document.getElementById('resumeTableBody');
    resumeTableBody.innerHTML = '<tr><td colspan="8" class="p-3 text-gray-600">Carregando...</td></tr>';
    const dataInicio = document.getElementById('resumeDataInicio').value;
    const dataFim = document.getElementById('resumeDataFim').value;
    const area = document.getElementById('areaFilter').value;
    const escolaridade = document.getElementById('escolaridadeFilter').value;
    const searchTerm = document.getElementById('searchInput').value;
    const params = new URLSearchParams();
    if (dataInicio) params.append('data_inicio', dataInicio);
    if (dataFim) params.append('data_fim', dataFim);
    if (area && area !== 'Todas as áreas') params.append('area', area);
    if (escolaridade && escolaridade !== 'Todas') params.append('escolaridade', escolaridade);
    if (searchTerm) params.append('search', searchTerm);
    const url = `${getApiBaseUrl()}/get_resumes.php${params.toString() ? '?' + params.toString() : ''}`;
    const response = await fetch(url, { method: 'GET', headers: { 'Content-Type': 'application/json' } });
    if (!response.ok) throw new Error('Erro HTTP: ' + response.status);
    const data = await response.json();
    if (data.error) throw new Error(data.error);
    resumes = data;
    renderTable(resumes);
  } catch (error) {
    console.error('Erro ao buscar currículos:', error);
    document.getElementById('resumeTableBody').innerHTML = `<tr><td colspan="4" class="p-3 text-red-600">Erro: ${error.message}</td></tr>`;
  }
}

function renderTable(data) {
  const resumeTableBody = document.getElementById('resumeTableBody');
  resumeTableBody.innerHTML = data.length > 0
    ? data.map((resume, index) => `
      <tr class="border-b border-gray-200">
        <td class="p-3">${resume.created_at ? new Date(resume.created_at).toLocaleDateString('pt-BR') : '-'}</td>
        <td class="p-3">${resume.nome}</td>
        <td class="p-3">${resume.email}</td>
        <td class="p-3">${resume.escolaridade || '-'}</td>
        <td class="p-3 flex flex-col sm:flex-row gap-2">
          <button class="view-details bg-accent text-white py-1.5 px-4 rounded-lg font-medium hover:bg-blue-700 transition" data-index="${index}">Ver</button>
          <a href="${resume.curriculo}" download="${resume.curriculo !== '#' ? resume.curriculo.split('/').pop() : 'curriculo.pdf'}" class="bg-secondary text-white py-1.5 px-4 rounded-lg font-medium hover:bg-gray-600 transition" ${resume.curriculo === '#' ? 'style="pointer-events: none; opacity: 0.6;"' : ''}>Currículo</a>
        </td>
      </tr>
    `).join('')
    : '<tr><td colspan="5" class="p-3 text-gray-600">Nenhum currículo registrado.</td></tr>';
  document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
      const idx = this.getAttribute('data-index');
      showDetails(resumes[idx]);
    });
  });
}

async function showDetails(resume) {
  let detalhes = null;
  try {
    const response = await fetch(`${getApiBaseUrl()}/get_candidato_detalhes.php?email=${encodeURIComponent(resume.email)}`);
    if (response.ok) {
      detalhes = await response.json();
      if (detalhes.error) throw new Error(detalhes.message);
    } else {
      throw new Error('Erro ao buscar detalhes do candidato');
    }
  } catch (e) {
    alert('Erro ao buscar detalhes do candidato: ' + e.message);
    return;
  }

  const c = detalhes.candidato || {};
  const contato = detalhes.contato || {};
  const preferencias = detalhes.preferencias || {};
  const qualificacoes = detalhes.qualificacoes || [];
  const experiencias = detalhes.experiencias || [];
  const vagasInscritas = detalhes.vagas_inscritas || [];

  let modal = document.getElementById('curriculoDetailsModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'curriculoDetailsModal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(0,0,0,0.5)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';
    document.body.appendChild(modal);
  }
  modal.innerHTML = `
    <div style="background:#fff;max-width:650px;width:98vw;padding:0;border-radius:18px;box-shadow:0 2px 24px #0002;position:relative;overflow:hidden;max-height:92vh;display:flex;flex-direction:column;">
      <div style="background:#2563eb;color:#fff;padding:20px 32px 16px 32px;display:flex;align-items:center;justify-content:space-between;position:relative;">
        <div style="display:flex;align-items:center;gap:12px;">
          <i class="fas fa-id-card" style="font-size:1.5rem;"></i>
          <h2 style="font-size:1.3rem;font-weight:600;margin:0;">Ficha do Candidato</h2>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
          <button id="downloadFichaBtn" title="Baixar Ficha em PDF" style="background:#fff;border:none;border-radius:8px;padding:7px 16px;font-weight:600;color:#2563eb;box-shadow:0 1px 4px #0001;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:1rem;transition:background 0.2s;">
            <i class="fas fa-file-pdf"></i> Baixar Ficha
          </button>
          <button id="closeCurriculoModal" style="background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:38px;height:38px;font-size:24px;cursor:pointer;color:#fff;transition:background 0.2s;">&times;</button>
        </div>
      </div>
      <div style="padding:24px 32px;overflow-y:auto;flex:1;">
        <section style="margin-bottom:18px;">
          <h3 style="font-size:1.1rem;font-weight:600;margin-bottom:8px;color:#2563eb;display:flex;align-items:center;gap:8px;"><i class='fas fa-user'></i> Dados Pessoais</h3>
          <div style="font-size:1rem;line-height:1.7;display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
            <p><b>Nome:</b> ${c.nome || '-'}</p>
            <p><b>E-mail:</b> ${c.email || '-'}</p>
            <p><b>Telefone:</b> ${contato.telefone || '-'}</p>
            <p><b>Data de Nascimento:</b> ${contato.nascimento ? new Date(contato.nascimento).toLocaleDateString('pt-BR') : '-'}</p>
            <p><b>Cidade:</b> ${contato.cidade || '-'}</p>
            <p><b>Estado:</b> ${contato.estado || '-'}</p>
            <p><b>Escolaridade:</b> ${c.escolaridade || '-'}</p>
            <p><b>Status Escolaridade:</b> ${c.status_escolaridade || '-'}</p>
            <p><b>Área de Interesse:</b> ${preferencias.area_interesse || '-'}</p>
            <p><b>LinkedIn:</b> ${preferencias.linkedin ? `<a href='${preferencias.linkedin}' target='_blank' style='color:#2563eb;'>${preferencias.linkedin}</a>` : '-'}</p>
            <p><b>Pretensão Salarial:</b> ${preferencias.pretensao_salarial || '-'}</p>
            <p><b>Disponibilidade:</b> ${preferencias.disponibilidade || '-'}</p>
            <p><b>LGPD:</b> ${c.lgpd ? 'Aceito' : 'Não aceito'}</p>
          </div>
        </section>
        <hr style="margin:18px 0;">
        <section style="margin-bottom:18px;">
          <h3 style="font-size:1.1rem;font-weight:600;margin-bottom:8px;color:#2563eb;display:flex;align-items:center;gap:8px;"><i class='fas fa-graduation-cap'></i> Qualificações</h3>
          ${qualificacoes.length > 0 ? qualificacoes.map(q => `
            <div style="margin-bottom:8px;padding:8px 12px;background:#f3f4f6;border-radius:8px;">
              <b>${q.descricao || '-'}</b><br>
              <span style="font-size:0.95em;">${q.instituicao || ''} ${q.ano_conclusao ? ' - ' + q.ano_conclusao : ''}</span>
            </div>
          `).join('') : '<span style="color:#888;">Nenhuma qualificação registrada.</span>'}
        </section>
        <hr style="margin:18px 0;">
        <section style="margin-bottom:18px;">
          <h3 style="font-size:1.1rem;font-weight:600;margin-bottom:8px;color:#2563eb;display:flex;align-items:center;gap:8px;"><i class='fas fa-briefcase'></i> Histórico Profissional</h3>
          ${experiencias.length > 0 ? experiencias.map(e => `
            <div style="margin-bottom:8px;padding:8px 12px;background:#f3f4f6;border-radius:8px;">
              <b>${e.cargo || '-'}${e.empresa ? ' - ' + e.empresa : ''}</b><br>
              <span style="font-size:0.95em;">${e.inicio ? new Date(e.inicio).toLocaleDateString('pt-BR') : '-'} a ${e.termino && e.termino !== '1900-01-01' ? new Date(e.termino).toLocaleDateString('pt-BR') : (e.termino === '1900-01-01' ? 'Atualmente' : '-')}</span><br>
              <span style="font-size:0.95em;">${e.responsabilidades || ''}</span>
            </div>
          `).join('') : '<span style="color:#888;">Nenhuma experiência registrada.</span>'}
        </section>
        <hr style="margin:18px 0;">
        <section>
          <h3 style="font-size:1.1rem;font-weight:600;margin-bottom:8px;color:#2563eb;display:flex;align-items:center;gap:8px;"><i class='fas fa-clipboard-list'></i> Vagas Inscritas</h3>
          ${vagasInscritas.length > 0 ? vagasInscritas.map(v => `<div style='margin-bottom:8px;padding:8px 12px;background:#f3f4f6;border-radius:8px;'><b>${v.title || v.titulo || '-'}</b> <span style='font-size:0.95em;color:#666;'>${v.company || v.empresa || ''}</span> <span style='font-size:0.95em;color:#666;'>${v.data_inscricao ? ' - ' + new Date(v.data_inscricao).toLocaleDateString('pt-BR') : ''}</span></div>`).join('') : '<span style="color:#888;">Nenhuma inscrição encontrada.</span>'}
        </section>
      </div>
    </div>
  `;

  modal.onclick = function(e) {
    if (e.target === modal || e.target.id === 'closeCurriculoModal') {
      modal.remove();
    }
  };
  document.getElementById('downloadFichaBtn').onclick = async function(e) {
    e.stopPropagation();
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
    try {
      const url = `${getApiBaseUrl()}/download_ficha_candidato.php?email=${encodeURIComponent(c.email)}`;
      const response = await fetch(url, { method: 'GET' });
      if (!response.ok) throw new Error('Erro ao gerar ficha PDF');
      const blob = await response.blob();
      if (blob.size === 0) throw new Error('Arquivo PDF vazio');
      const blobUrl = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = blobUrl;
      a.download = `Ficha_${c.nome ? c.nome.replace(/[^a-z0-9]/gi, '_') : 'candidato'}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(blobUrl);
      document.body.removeChild(a);
    } catch (err) {
      alert('Erro ao baixar ficha: ' + err.message);
    } finally {
      btn.innerHTML = '<i class="fas fa-file-pdf"></i> Baixar Ficha';
      btn.disabled = false;
    }
  };
}

window.addEventListener('DOMContentLoaded', function() {
  const firstDay = new Date();
  firstDay.setDate(1);
  const today = new Date();
  const resumeDataInicio = document.getElementById('resumeDataInicio');
  const resumeDataFim = document.getElementById('resumeDataFim');
  if (resumeDataInicio && resumeDataFim) {
    resumeDataInicio.value = firstDay.toISOString().split('T')[0];
    resumeDataFim.value = today.toISOString().split('T')[0];
  }
  loadAreas();
  fetchResumes();
  document.getElementById('applyFilters').addEventListener('click', fetchResumes);
  document.getElementById('searchInput').addEventListener('input', function() {
    fetchResumes();
  });

  document.getElementById('downloadFiltered').addEventListener('click', async function() {
    const downloadButton = this;
    downloadButton.disabled = true;
    downloadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Baixando...';
    try {
      const dataInicio = document.getElementById('resumeDataInicio').value;
      const dataFim = document.getElementById('resumeDataFim').value;
      const area = document.getElementById('areaFilter').value;
      const escolaridade = document.getElementById('escolaridadeFilter').value;
      const searchTerm = document.getElementById('searchInput').value;
      const params = new URLSearchParams();
      if (dataInicio) params.append('data_inicio', dataInicio);
      if (dataFim) params.append('data_fim', dataFim);
      if (area && area !== 'Todas as áreas') params.append('area', area);
      if (escolaridade && escolaridade !== 'Todas') params.append('escolaridade', escolaridade);
      if (searchTerm) params.append('search', searchTerm);
      const response = await fetch(`${getApiBaseUrl()}/download_daily_resumes.php${params.toString() ? '?' + params.toString() : ''}`, {
        method: 'GET',
        headers: { 'Accept': 'application/zip, application/json' }
      });
      if (!response.ok) throw new Error(`Erro ${response.status}: ${response.statusText}`);
      const blob = await response.blob();
      if (blob.size === 0) throw new Error('O arquivo ZIP está vazio');
      const blobUrl = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = blobUrl;
      let fileName = 'curriculos';
      if (dataInicio || dataFim) fileName += '_' + (dataInicio || 'inicio') + '_ate_' + (dataFim || 'fim');
      if (area && area !== 'Todas as áreas') fileName += '_' + area.replace(/[^a-z0-9]/gi, '_');
      if (escolaridade && escolaridade !== 'Todas') fileName += '_' + escolaridade.replace(/[^a-z0-9]/gi, '_');
      fileName += '.zip';
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(blobUrl);
      document.body.removeChild(a);
    } catch (error) {
      console.error('Erro ao baixar currículos:', error);
      alert('Erro ao baixar currículos: ' + error.message);
    } finally {
      downloadButton.innerHTML = '<i class="fas fa-download"></i> Baixar Currículos Filtrados';
      downloadButton.disabled = false;
    }
  });
  document.getElementById('downloadFiltered').disabled = false;
  document.getElementById('searchButton').addEventListener('click', function() {
    fetchResumes();
  });
}); 