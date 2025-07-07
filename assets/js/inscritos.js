// inscritos.js 

document.addEventListener('DOMContentLoaded', () => {
  const inscritosTableBody = document.getElementById('inscritosTableBody');
  const loadingInscritos = document.getElementById('loadingInscritos');
  const searchInput = document.getElementById('searchInscritosInput');
  let inscritos = [];

  async function fetchInscritos() {
    loadingInscritos.style.display = '';
    inscritosTableBody.innerHTML = '';
    try {
      const resp = await fetch('api/get_inscritos.php');
      const data = await resp.json();
      inscritos = Array.isArray(data) ? data : [];
      renderInscritos();
    } catch (err) {
      inscritosTableBody.innerHTML = `<tr><td colspan='7' class='p-3 text-red-600'>Erro ao carregar inscritos</td></tr>`;
    }
    loadingInscritos.style.display = 'none';
  }

  function renderInscritos() {
    const filtro = (searchInput.value || '').toLowerCase();
    const filtrados = inscritos.filter(v =>
      (v.title && v.title.toLowerCase().includes(filtro)) ||
      (v.company && v.company.toLowerCase().includes(filtro))
    );
    if (!filtrados.length) {
      inscritosTableBody.innerHTML = `<tr><td colspan='7' class='p-3 text-gray-600'>Nenhuma vaga encontrada.</td></tr>`;
      return;
    }
    inscritosTableBody.innerHTML = filtrados.map(vaga => {
      const diasRestantes = calcularDiasRestantes(vaga.deadline);
      const statusClasses = {
        'Fechada': 'bg-green-100 text-green-800',
        'Suspensa': 'bg-red-100 text-red-800',
        'Em Divulgação': 'bg-yellow-100 text-yellow-800',
        'Aguardando Retorno': 'bg-gray-100 text-gray-800',
      };
      const statusClass = statusClasses[vaga.status] || 'bg-gray-100 text-gray-800';
      const diasText = diasRestantes > 0 ? `${diasRestantes} dias` : diasRestantes === 0 ? 'Hoje' : 'Encerrado';
      const prazoFormatado = vaga.deadline ? new Date(vaga.deadline).toLocaleDateString('pt-BR') : 'N/A';
      return `
        <tr class="border-b border-gray-200">
          <td class="p-3">${vaga.title || '-'}</td>
          <td class="p-3">${vaga.company || '-'}</td>
          <td class="p-3"><span class="status-badge ${statusClass}">${vaga.status || 'Indefinido'}</span></td>
          <td class="p-3">${prazoFormatado}</td>
          <td class="p-3 ${diasRestantes <= 3 ? 'text-red-600 font-medium' : 'text-gray-700'}">${diasText}</td>
          <td class="p-3 text-center">${vaga.total_inscritos || 0}</td>
          <td class="p-3">
            <button class="view-candidates bg-accent text-white font-medium" data-id="${vaga.id}">Ver Candidatos</button>
          </td>
        </tr>
      `;
    }).join('');
    document.querySelectorAll('.view-candidates').forEach(btn => {
      btn.onclick = function() {
        // Aqui pode abrir modal ou redirecionar para detalhes dos candidatos
        alert('Funcionalidade de ver candidatos em breve!');
      };
    });
  }

  function calcularDiasRestantes(deadline) {
    if (!deadline) return '-';
    try {
      const deadlineDate = new Date(deadline);
      const today = new Date();
      today.setHours(0,0,0,0);
      const diff = deadlineDate - today;
      return Math.ceil(diff / (1000 * 60 * 60 * 24));
    } catch {
      return '-';
    }
  }

  searchInput.addEventListener('input', renderInscritos);
  fetchInscritos();
}); 