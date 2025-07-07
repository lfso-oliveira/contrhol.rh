// processo_seletivo.js 

document.addEventListener('DOMContentLoaded', () => {
  const contentArea = document.getElementById('processo-seletivo-content-area');
  const loading = document.getElementById('loadingProcessoSeletivo');
  const vagaBuscaInput = document.getElementById('vagaBuscaProcessoSeletivo');
  const vagaDropdown = document.getElementById('vagaDropdownProcessoSeletivo');
  let todasVagas = [];
  let vagaSelecionada = null;

  // Etapas possíveis do processo (exatamente como backend aceita)
  const etapas = [
    'Inscrito',
    'Triagem',
    'Entrevista Agendada',
    'Entrevistado',
    'Aprovado',
    'Rejeitado',
    'Contratado'
  ];

  async function carregarVagas() {
    loading.style.display = '';
    try {
      const resp = await fetch('api/get_jobs.php');
      const data = await resp.json();
      todasVagas = Array.isArray(data) ? data : [];
    } catch (err) {
      todasVagas = [];
    }
    loading.style.display = 'none';
  }

  function renderVagaDropdown(filtro = '') {
    const vagasFiltradas = todasVagas.filter(v =>
      v.title.toLowerCase().includes(filtro.toLowerCase()) ||
      (v.company && v.company.toLowerCase().includes(filtro.toLowerCase()))
    );
    if (!vagasFiltradas.length) {
      vagaDropdown.innerHTML = '<div class="p-3 text-gray-500">Nenhuma vaga encontrada</div>';
      vagaDropdown.classList.remove('hidden');
      return;
    }
    vagaDropdown.innerHTML = vagasFiltradas.map((v, i) =>
      `<div class="px-4 py-2 cursor-pointer hover:bg-blue-50 flex items-center gap-2" data-idx="${i}"><i class="fas fa-briefcase text-blue-400"></i> <span class="font-semibold">${v.title}</span> <span class="text-xs text-gray-500 ml-2">(${v.company})</span></div>`
    ).join('');
    vagaDropdown.classList.remove('hidden');
  }

  vagaBuscaInput.oninput = function() {
    renderVagaDropdown(this.value);
  };
  vagaBuscaInput.onfocus = function() {
    renderVagaDropdown(this.value);
  };
  vagaBuscaInput.onblur = function() {
    setTimeout(()=>vagaDropdown.classList.add('hidden'), 180);
  };
  vagaBuscaInput.onkeydown = function(e) {
    if (e.key === 'Enter' && todasVagas.length) {
      selecionarVagaCombo(0);
    }
  };
  vagaDropdown.onclick = function(e) {
    const el = e.target.closest('[data-idx]');
    if (el) selecionarVagaCombo(Number(el.dataset.idx));
  };
  function selecionarVagaCombo(idx) {
    const vagasFiltradas = todasVagas.filter(v =>
      v.title.toLowerCase().includes(vagaBuscaInput.value.toLowerCase()) ||
      (v.company && v.company.toLowerCase().includes(vagaBuscaInput.value.toLowerCase()))
    );
    const vaga = vagasFiltradas[idx];
    if (!vaga) return;
    vagaSelecionada = vaga;
    vagaBuscaInput.value = vaga.title + ' (' + vaga.company + ')';
    vagaDropdown.classList.add('hidden');
    renderProcessoSeletivo();
  }

  async function renderProcessoSeletivo() {
    contentArea.innerHTML = '';
    if (!vagaSelecionada) return;
    loading.style.display = '';
    let vagasFiltradas = todasVagas;
    if (vagaSelecionada) vagasFiltradas = todasVagas.filter(v => v.id == vagaSelecionada.id);
    if (!vagasFiltradas.length) {
      contentArea.innerHTML = '<div class="text-gray-500 p-6">Nenhuma vaga encontrada.</div>';
      loading.style.display = 'none';
      return;
    }
    for (const vaga of vagasFiltradas) {
      // Buscar candidatos desta vaga
      let candidatos = [];
      try {
        const resp = await fetch(`api/get_candidates.php?jobId=${vaga.id}`);
        const data = await resp.json();
        candidatos = Array.isArray(data.candidates) ? data.candidates : [];
      } catch {
        contentArea.innerHTML += `<div class='bg-white rounded-xl shadow p-6 mb-8'><h3 class='text-lg font-bold mb-2'>${vaga.title} <span class='text-gray-500 text-base'>(${vaga.company})</span></h3><div class='text-red-600'>Erro ao carregar candidatos.</div></div>`;
        continue;
      }
      // Agrupar candidatos por etapa
      let grupos = {};
      etapas.forEach(etapa => grupos[etapa] = []);
      candidatos.forEach(c => {
        const etapa = etapas.includes(c.status_selecao) ? c.status_selecao : etapas[0];
        grupos[etapa].push(c);
      });
      // Renderizar Kanban
      let html = `<div class='kanban-container flex gap-6 overflow-x-auto pb-4'>`;
      etapas.forEach(etapa => {
        html += `<div class='kanban-col flex-1 min-w-[260px] bg-blue-50 rounded-xl p-3 border border-blue-100 flex flex-col' data-etapa='${etapa}'>
          <div class='flex items-center justify-between mb-2'>
            <span class='font-bold text-blue-900 text-base flex items-center gap-2'><i class='fas fa-layer-group'></i> ${etapa}</span>
            <button class='add-candidato-btn px-2 py-1 rounded bg-blue-500 text-white text-xs font-semibold hover:bg-blue-700 transition' data-vaga='${vaga.id}' data-etapa='${etapa}'><i class='fas fa-user-plus'></i></button>
          </div>
          <div class='kanban-list flex-1 flex flex-col gap-2' id='kanban-list-${etapa.replace(/\s/g,"")}' style='min-height:60px;'>`;
        if (grupos[etapa].length) {
          html += grupos[etapa].map(c => `
            <div class='kanban-card bg-white rounded-lg shadow p-3 border border-blue-200 flex flex-col gap-1 cursor-move ${etapa==='Entrevistado' ? ((c.foi_entrevista==1||c.foi_entrevista=='1') ? 'bg-green-100' : (c.foi_entrevista==0||c.foi_entrevista=='0') ? 'bg-yellow-100' : '') : ''}' draggable='true' data-id='${c.id_candidatura_vaga}'>
              <span class='font-semibold text-blue-800'>${c.nome || '-'}</span>
              ${(etapa === 'Entrevista Agendada' || etapa === 'Entrevistado') && c.data_entrevista ? `<span class='text-xs text-blue-600 mt-1'><i class='fas fa-calendar-alt'></i> ${formatarDataHoraEntrevista(c.data_entrevista)}</span>` : ''}
              ${etapa === 'Entrevistado' && c.observacoes_entrevista ? `<span class='text-xs text-gray-700 mt-1'><i class='fas fa-comment-dots'></i> ${c.observacoes_entrevista}</span>` : ''}
              <div class='flex gap-1 mt-2'>
                <button class='px-2 py-1 rounded bg-red-500 text-white text-xs font-semibold hover:bg-red-700 transition excluir-candidato-btn' title='Excluir' data-vaga='${vaga.id}' data-email='${c.email}'><i class='fas fa-trash'></i></button>
                <button class='px-2 py-1 rounded bg-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-400 transition' title='Detalhes'><i class='fas fa-eye'></i></button>
              </div>
            </div>
          `).join('');
        } else {
          html += `<div class='text-xs text-gray-400 text-center py-4'>Nenhum candidato nesta etapa.</div>`;
        }
        html += `</div></div>`;
      });
      html += `</div>`;
      contentArea.innerHTML += html;
      window.ultimaListaCandidatos = candidatos;
    }
    loading.style.display = 'none';
    // Eventos dos botões
    document.querySelectorAll('.add-candidato-btn').forEach(btn => {
      btn.onclick = function() { abrirModalAdicionar(this.dataset.vaga, this.dataset.etapa); };
    });
    document.querySelectorAll('.excluir-candidato-btn').forEach(btn => {
      btn.onclick = function() { excluirCandidato(this.dataset.vaga, this.dataset.email); };
    });
    document.querySelectorAll('.kanban-card .excluir-candidato-btn').forEach(btn => {
      btn.onclick = function(e) { e.stopPropagation(); excluirCandidato(this.dataset.vaga, this.dataset.email); };
    });
    document.querySelectorAll('.kanban-card .fa-eye').forEach(btn => {
      btn.onclick = async function(e) {
        e.stopPropagation();
        const card = this.closest('.kanban-card');
        if (!card) return;
        const email = card.querySelector('.excluir-candidato-btn').dataset.email;
        if (!email) return;
        try {
          const resp = await fetch(`api/get_candidato_detalhes.php?email=${encodeURIComponent(email)}`);
          const data = await resp.json();
          if (data && data.candidato) {
            showCandidatoDetalhesModalKanban(data);
          } else {
            alert('Não foi possível carregar os detalhes do candidato.');
          }
        } catch {
          alert('Erro ao buscar detalhes do candidato.');
        }
      };
    });
    // Drag and drop com SortableJS
    etapas.forEach(etapa => {
      const list = document.getElementById('kanban-list-' + etapa.replace(/\s/g, ""));
      if (!list) return;
      new Sortable(list, {
        group: 'kanban-candidatos',
        animation: 180,
        ghostClass: 'bg-blue-100',
        onAdd: function (evt) {
          const card = evt.item;
          const idCandidatura = card.dataset.id;
          const novaEtapa = etapa;
          if (novaEtapa === 'Entrevista Agendada') {
            abrirModalDataEntrevista(idCandidatura, function(dataEntrevista) {
              atualizarEtapa(idCandidatura, novaEtapa, null, true, dataEntrevista);
            }, function() {
              evt.from.appendChild(card);
            });
          } else if (novaEtapa === 'Entrevistado') {
            abrirModalEntrevistado(idCandidatura, function(foiEntrevista, obsEntrevista) {
              atualizarEtapa(idCandidatura, novaEtapa, null, true, null, foiEntrevista, obsEntrevista);
            }, function() {
              evt.from.appendChild(card);
            });
          } else {
            atualizarEtapa(idCandidatura, novaEtapa, null, true);
          }
        }
      });
    });
  }

  function abrirModalAdicionar(vagaId, etapa) {
    if (document.getElementById('modalAddCandidato')) document.getElementById('modalAddCandidato').remove();
    const modal = document.createElement('div');
    modal.id = 'modalAddCandidato';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(30,41,59,0.22)';
    modal.style.backdropFilter = 'blur(4px)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';
    modal.innerHTML = `<div style='background:#fff;max-width:380px;width:96vw;padding:32px 28px 22px 28px;border-radius:18px;box-shadow:0 8px 32px #2563eb33;position:relative;'>
      <h3 style='font-size:1.2rem;font-weight:700;margin-bottom:18px;color:#2563eb;display:flex;align-items:center;gap:8px;'><i class='fas fa-user-plus'></i> Adicionar Participante (${etapa || 'Inscrito'})</h3>
      <form id='formAddCandidato' autocomplete='off'>
        <label class='block mb-1 text-sm font-medium text-gray-700'>Buscar candidato</label>
        <div class='relative mb-3'>
          <input type='text' id='autocompleteCandidato' placeholder='Digite nome ou e-mail...' class='w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition' autocomplete='off' />
          <div id='dropdownCandidato' class='absolute z-20 left-0 right-0 mt-2 bg-white border border-blue-100 rounded-xl shadow-lg max-h-60 overflow-y-auto hidden'></div>
        </div>
        <input type='hidden' name='nome' />
        <input type='hidden' name='email' required />
        <input type='hidden' name='telefone' />
        <input type='hidden' name='status_selecao' value='${etapa || 'Inscrito'}' />
        <div class='flex justify-end gap-2 mt-4'>
          <button type='button' id='cancelAddCandidato' class='px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition'>Cancelar</button>
          <button type='submit' class='px-4 py-2 rounded bg-blue-500 text-white font-semibold hover:bg-blue-700 transition'>Adicionar</button>
        </div>
      </form>
    </div>`;
    document.body.appendChild(modal);
    document.getElementById('cancelAddCandidato').onclick = () => modal.remove();

    // Autocomplete de candidatos
    const inputAuto = document.getElementById('autocompleteCandidato');
    const dropdown = document.getElementById('dropdownCandidato');
    let candidatosEncontrados = [];
    inputAuto.oninput = async function() {
      const termo = this.value.trim();
      if (termo.length < 2) {
        dropdown.classList.add('hidden');
        return;
      }
      // Buscar candidatos já cadastrados
      try {
        const resp = await fetch('api/search_candidatos.php?q=' + encodeURIComponent(termo));
        const data = await resp.json();
        const lista = Array.isArray(data.data) ? data.data : [];
        candidatosEncontrados = lista;
        if (!candidatosEncontrados.length) {
          dropdown.innerHTML = '<div class="p-3 text-gray-500">Nenhum candidato encontrado</div>';
          dropdown.classList.remove('hidden');
          return;
        }
        dropdown.innerHTML = candidatosEncontrados.map((c, i) =>
          `<div class="px-4 py-2 cursor-pointer hover:bg-blue-50 flex flex-col" data-idx="${i}"><span class="font-semibold text-blue-800">${c.nome}</span><span class="text-xs text-gray-500">${c.email}</span></div>`
        ).join('');
        dropdown.classList.remove('hidden');
      } catch {
        dropdown.innerHTML = '<div class="p-3 text-red-600">Erro ao buscar candidatos</div>';
        dropdown.classList.remove('hidden');
      }
    };
    inputAuto.onfocus = function() {
      if (this.value.length >= 2) dropdown.classList.remove('hidden');
    };
    inputAuto.onblur = function() {
      setTimeout(()=>dropdown.classList.add('hidden'), 180);
    };
    dropdown.onclick = function(e) {
      const el = e.target.closest('[data-idx]');
      if (el) selecionarCandidato(Number(el.dataset.idx));
    };
    function selecionarCandidato(idx) {
      const c = candidatosEncontrados[idx];
      if (!c) return;
      inputAuto.value = c.nome + ' (' + c.email + ')';
      document.querySelector('#formAddCandidato [name=nome]').value = c.nome;
      document.querySelector('#formAddCandidato [name=email]').value = c.email;
      document.querySelector('#formAddCandidato [name=telefone]').value = c.telefone || '';
      dropdown.classList.add('hidden');
    }

    document.getElementById('formAddCandidato').onsubmit = async function(e) {
      e.preventDefault();
      const form = e.target;
      const nome = form.nome.value.trim();
      const email = form.email.value.trim();
      const telefone = form.telefone.value.trim();
      const status_selecao = form.status_selecao.value;
      if (!nome || !email) return alert('Selecione um candidato existente!');
      try {
        const resp = await fetch('api/add_candidato_processo.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ vaga_id: vagaId, candidato_email: email, status_selecao })
        });
        const data = await resp.json();
        if (data.success) {
          modal.remove();
          renderProcessoSeletivo();
        } else {
          alert(data.error || data.message || 'Erro ao adicionar candidato.');
        }
      } catch {
        alert('Erro ao adicionar candidato.');
      }
    };
  }

  async function excluirCandidato(vagaId, email) {
    // Encontrar o id_candidatura_vaga do candidato pelo email na lista atual
    let idCandidatura = null;
    if (vagaSelecionada && window.ultimaListaCandidatos) {
      const cand = window.ultimaListaCandidatos.find(c => c.email === email);
      if (cand) idCandidatura = cand.id_candidatura_vaga;
    }
    if (!idCandidatura) {
      alert('Não foi possível identificar o candidato para remover.');
      return;
    }
    if (!confirm('Tem certeza que deseja excluir este candidato da vaga?')) return;
    try {
      const resp = await fetch('api/remove_candidato_processo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_candidatura_vaga: idCandidatura })
      });
      const data = await resp.json();
      if (data.success) {
        renderProcessoSeletivo();
      } else {
        alert(data.message || 'Erro ao excluir candidato.');
      }
    } catch {
      alert('Erro ao excluir candidato.');
    }
  }

  function abrirModalDataEntrevista(idCandidatura, onConfirm, onCancel) {
    if (document.getElementById('modalDataEntrevista')) document.getElementById('modalDataEntrevista').remove();
    const modal = document.createElement('div');
    modal.id = 'modalDataEntrevista';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(30,41,59,0.22)';
    modal.style.backdropFilter = 'blur(4px)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';
    modal.innerHTML = `<div style='background:#fff;max-width:340px;width:96vw;padding:32px 28px 22px 28px;border-radius:18px;box-shadow:0 8px 32px #2563eb33;position:relative;'>
      <h3 style='font-size:1.1rem;font-weight:700;margin-bottom:18px;color:#2563eb;display:flex;align-items:center;gap:8px;'><i class='fas fa-calendar-alt'></i> Agendar Entrevista</h3>
      <form id='formDataEntrevista'>
        <label class='block mb-1 text-sm font-medium text-gray-700'>Data e horário</label>
        <input type='datetime-local' name='data_entrevista' required class='w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition' />
        <div class='flex justify-end gap-2 mt-4'>
          <button type='button' id='cancelDataEntrevista' class='px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition'>Cancelar</button>
          <button type='submit' class='px-4 py-2 rounded bg-blue-500 text-white font-semibold hover:bg-blue-700 transition'>Salvar</button>
        </div>
      </form>
    </div>`;
    document.body.appendChild(modal);
    document.getElementById('cancelDataEntrevista').onclick = () => { modal.remove(); if(onCancel) onCancel(); };
    document.getElementById('formDataEntrevista').onsubmit = function(e) {
      e.preventDefault();
      const data = this.data_entrevista.value;
      if (!data) return alert('Preencha a data e o horário!');
      modal.remove();
      if (onConfirm) onConfirm(data.replace('T',' ') + ':00');
    };
  }

  function abrirModalEntrevistado(idCandidatura, onConfirm, onCancel) {
    if (document.getElementById('modalEntrevistado')) document.getElementById('modalEntrevistado').remove();
    const modal = document.createElement('div');
    modal.id = 'modalEntrevistado';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(30,41,59,0.22)';
    modal.style.backdropFilter = 'blur(4px)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';
    modal.innerHTML = `<div style='background:#fff;max-width:380px;width:96vw;padding:32px 28px 22px 28px;border-radius:18px;box-shadow:0 8px 32px #2563eb33;position:relative;'>
      <h3 style='font-size:1.1rem;font-weight:700;margin-bottom:18px;color:#2563eb;display:flex;align-items:center;gap:8px;'><i class='fas fa-comments'></i> Entrevista Realizada?</h3>
      <form id='formEntrevistado'>
        <label class='block mb-1 text-sm font-medium text-gray-700'>O candidato foi à entrevista?</label>
        <select name='foi_entrevista' required class='w-full border border-gray-300 rounded-lg px-3 py-2 mb-3'>
          <option value=''>Selecione...</option>
          <option value='1'>Sim</option>
          <option value='0'>Não</option>
        </select>
        <label class='block mb-1 text-sm font-medium text-gray-700'>Observações da entrevista</label>
        <textarea name='obs_entrevista' rows='3' class='w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition'></textarea>
        <div class='flex justify-end gap-2 mt-4'>
          <button type='button' id='cancelEntrevistado' class='px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition'>Cancelar</button>
          <button type='submit' class='px-4 py-2 rounded bg-blue-500 text-white font-semibold hover:bg-blue-700 transition'>Salvar</button>
        </div>
      </form>
    </div>`;
    document.body.appendChild(modal);
    document.getElementById('cancelEntrevistado').onclick = () => { modal.remove(); if(onCancel) onCancel(); };
    document.getElementById('formEntrevistado').onsubmit = function(e) {
      e.preventDefault();
      const foi = this.foi_entrevista.value;
      if (foi === '') return alert('Selecione se o candidato foi à entrevista!');
      const obs = this.obs_entrevista.value.trim();
      modal.remove();
      if (onConfirm) onConfirm(foi, obs);
    };
  }

  async function atualizarEtapa(id_candidatura_vaga, novaEtapa, selectEl, silencioso, data_entrevista, foi_entrevista, observacoes_entrevista) {
    if(selectEl) selectEl.disabled = true;
    try {
      const body = { id_candidatura_vaga, status_selecao: novaEtapa };
      if (data_entrevista) body.data_entrevista = data_entrevista;
      if (typeof foi_entrevista !== 'undefined') body.foi_entrevista = foi_entrevista;
      if (typeof observacoes_entrevista !== 'undefined') body.observacoes_entrevista = observacoes_entrevista;
      const resp = await fetch('api/update_candidatura_vaga.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await resp.json();
      if (data.success) {
        if(selectEl) selectEl.style.background = '#bbf7d0';
        if (!silencioso) {
          setTimeout(()=>{ if(selectEl) selectEl.style.background = ''; renderProcessoSeletivo(); }, 1200);
        } else {
          // Se for atualização de campos extras, recarrega o Kanban imediatamente
          if (data_entrevista || typeof foi_entrevista !== 'undefined' || typeof observacoes_entrevista !== 'undefined') {
            renderProcessoSeletivo();
          }
        }
      } else {
        if(selectEl) selectEl.style.background = '#fecaca';
        alert(data.message || 'Erro ao atualizar etapa.');
        setTimeout(()=>{ if(selectEl) selectEl.style.background = ''; renderProcessoSeletivo(); }, 1200);
      }
    } catch {
      if(selectEl) selectEl.style.background = '#fecaca';
      alert('Erro ao atualizar etapa.');
      setTimeout(()=>{ if(selectEl) selectEl.style.background = ''; renderProcessoSeletivo(); }, 1200);
    }
    if(selectEl) selectEl.disabled = false;
  }

  async function atualizarDataEntrevista(id_candidatura_vaga, data_entrevista, btn) {
    if(btn) btn.disabled = true;
    try {
      const resp = await fetch('api/update_candidatura_vaga.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_candidatura_vaga, data_entrevista })
      });
      const data = await resp.json();
      if (data.success) {
        if(btn) btn.style.background = '#bbf7d0';
        setTimeout(()=>{ if(btn) btn.style.background = ''; renderProcessoSeletivo(); }, 600);
      } else {
        if(btn) btn.style.background = '#fecaca';
        alert(data.message || 'Erro ao salvar data.');
        setTimeout(()=>{ if(btn) btn.style.background = ''; }, 1200);
      }
    } catch {
      if(btn) btn.style.background = '#fecaca';
      alert('Erro ao salvar data.');
      setTimeout(()=>{ if(btn) btn.style.background = ''; }, 1200);
    }
    if(btn) btn.disabled = false;
  }

  function showCandidatoDetalhesModalKanban(data) {
    let modal = document.getElementById('candidatoDetalhesModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'candidatoDetalhesModal';
      modal.style.position = 'fixed';
      modal.style.top = '0';
      modal.style.left = '0';
      modal.style.width = '100vw';
      modal.style.height = '100vh';
      modal.style.background = 'rgba(30,41,59,0.22)';
      modal.style.backdropFilter = 'blur(6px)';
      modal.style.display = 'flex';
      modal.style.alignItems = 'center';
      modal.style.justifyContent = 'center';
      modal.style.zIndex = '9999';
      document.body.appendChild(modal);
    }
    const c = data.candidato;
    const contato = data.contato || {};
    const preferencias = data.preferencias || {};
    const qualificacoes = data.qualificacoes || [];
    const experiencias = data.experiencias || [];
    const vagas = data.vagas_inscritas || [];
    modal.innerHTML = `<div class='modal-box animate-modal modal-elevate' style='background:linear-gradient(120deg,#f8fbff 80%,#e0e7ef 120%);max-width:650px;width:98vw;height:auto;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 16px 64px #2563eb33,0 2px 12px #0002;border-radius:32px;position:relative;overflow:hidden;border:2.5px solid #2563eb;transition:box-shadow .22s;'>
      <div style='background:linear-gradient(90deg,#2563eb 60%,#38bdf8 120%);color:#fff;padding:26px 44px 18px 44px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2;box-shadow:0 4px 16px #2563eb33;'>
        <h2 style='font-size:1.32rem;font-weight:800;margin:0;letter-spacing:0.01em;display:flex;align-items:center;gap:10px;'><i class="fas fa-user-circle"></i> Detalhes do Candidato</h2>
        <button id='closeCandidatoDetalhesModal' style='background:rgba(255,255,255,0.22);border:none;border-radius:50%;width:48px;height:48px;font-size:30px;cursor:pointer;color:#fff;transition:background 0.18s,box-shadow 0.18s;box-shadow:0 2px 8px #2563eb22;display:flex;align-items:center;justify-content:center;position:absolute;top:18px;right:18px;'><i class="fas fa-times"></i></button>
      </div>
      <div style='flex:1;overflow-y:auto;padding:36px 44px;display:flex;flex-direction:column;gap:22px;'>
        <div style='display:flex;align-items:center;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-id-badge"></i> Nome:</span><br><span style='font-size:1.13em;'>${c.nome || '-'}</span></div>
        <div style='border-bottom:1px solid #e0e7ef;padding-bottom:10px;display:flex;align-items:center;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-envelope"></i> E-mail:</span><br><span style='font-size:1.13em;'>${c.email || '-'}</span></div>
        <div style='display:flex;align-items:center;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-phone"></i> Telefone:</span><br>${contato.telefone || '-'}</div>
        <div style='display:flex;align-items:center;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-graduation-cap"></i> Escolaridade:</span><br>${c.escolaridade || '-'}</div>
        <div style='display:flex;align-items:center;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-bullseye"></i> Área de Interesse:</span><br>${preferencias.area_interesse || '-'}</div>
        <div style='display:flex;align-items:flex-start;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-certificate"></i> Qualificações:</span><br><span style='white-space:pre-line;'>${qualificacoes.map(q => `${q.descricao} (${q.instituicao}, ${q.ano_conclusao})`).join('<br>') || '-'}</span></div>
        <div style='display:flex;align-items:flex-start;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-briefcase"></i> Experiências:</span><br><span style='white-space:pre-line;'>${experiencias.map(e => `${e.cargo} na ${e.empresa} (${e.inicio} - ${e.termino}): ${e.responsabilidades}`).join('<br>') || '-'}</span></div>
        <div style='display:flex;align-items:flex-start;gap:10px;'><span style='color:#2563eb;font-weight:700;'><i class="fas fa-comment-dots"></i> Observação da Entrevista:</span><br><span style='white-space:pre-line;'>${c.observacoes_entrevista || '-'}</span></div>
        <div class='mt-2 text-xs text-gray-500'><b>Vagas inscritas:</b> ${vagas.map(v => `<span class='inline-block bg-blue-100 text-blue-800 rounded px-2 py-0.5 mr-1 mb-1'>${v.title} <small>(${v.company})</small></span>`).join('') || 'Nenhuma'}</div>
      </div>
    </div>`;
    const modalBox = modal.querySelector('.modal-box');
    // Animação de entrada
    modalBox.style.opacity = '0';
    modalBox.style.transform = 'translateY(48px) scale(0.97)';
    setTimeout(() => {
      modalBox.style.transition = 'all .28s cubic-bezier(.4,0,.2,1)';
      modalBox.style.opacity = '1';
      modalBox.style.transform = 'translateY(0) scale(1)';
    }, 10);
    modalBox.onmouseenter = () => { modalBox.style.boxShadow = '0 24px 80px #2563eb44,0 2px 12px #0002'; };
    modalBox.onmouseleave = () => { modalBox.style.boxShadow = '0 16px 64px #2563eb33,0 2px 12px #0002'; };
    modal.onclick = function(e) {
      if (e.target === modal || e.target.id === 'closeCandidatoDetalhesModal') modal.remove();
    };
  }

  function formatarDataHoraEntrevista(dt) {
    if (!dt) return '';
    const d = new Date(dt.replace(/-/g, '/'));
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  carregarVagas();
}); 