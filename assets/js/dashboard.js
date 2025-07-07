// dashboard.js

// Função para obter a URL base da API
function getApiBaseUrl() {
  return window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    ? '/public_html/dash/api'
    : 'api';
}

let candidatesChart = null;
let graficoPorHora = null;

function getUltimos30Dias() {
  const hoje = new Date();
  const fim = hoje.toISOString().slice(0,10);
  const inicioDate = new Date(hoje);
  inicioDate.setDate(hoje.getDate() - 29);
  const inicio = inicioDate.toISOString().slice(0,10);
  return {inicio, fim};
}

// Função para atualizar o dashboard
async function updateDashboard() {
  const {inicio, fim} = getUltimos30Dias();
  await loadStats(inicio, fim);
  await updateCandidatesChart(inicio, fim);
}

// Função para atualizar o gráfico de candidatos
async function updateCandidatesChart(dataInicio = null, dataFim = null) {
  try {
    const url = `${getApiBaseUrl()}/get_candidates_by_day.php` + 
      (dataInicio && dataFim ? `?data_inicio=${dataInicio}&data_fim=${dataFim}` : '');
    const response = await fetch(url);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();
    if (data.error) throw new Error(data.message || 'Erro ao buscar dados');
    if (!Array.isArray(data) || data.length === 0) {
      const canvas = document.getElementById('candidatesByDayChart');
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.font = '14px Arial';
      ctx.fillStyle = '#666666';
      ctx.textAlign = 'center';
      ctx.fillText('Nenhum dado encontrado para o período selecionado', canvas.width/2, canvas.height/2);
      return;
    }
    const ctx = document.getElementById('candidatesByDayChart').getContext('2d');
    if (candidatesChart) candidatesChart.destroy();

    const dark = isDarkMode();
    // Gradiente para a linha
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, dark ? 'rgba(59,130,246,0.22)' : 'rgba(37,99,235,0.22)');
    gradient.addColorStop(1, dark ? 'rgba(30,41,59,0.10)' : 'rgba(59,130,246,0.05)');

    candidatesChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map(item => item.data),
        datasets: [{
          label: 'Candidatos',
          data: data.map(item => item.quantidade),
          borderColor: dark ? '#60a5fa' : '#2563eb',
          backgroundColor: gradient,
          borderWidth: 3,
          fill: true,
          tension: 0.45,
          pointBackgroundColor: dark ? '#60a5fa' : '#2563eb',
          pointBorderColor: '#fff',
          pointBorderWidth: 2.5,
          pointRadius: 5,
          pointHoverRadius: 10,
          pointHoverBackgroundColor: dark ? '#1e40af' : '#1e40af',
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2.5,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 1500,
          easing: 'easeOutBounce'
        },
        interaction: { mode: 'nearest', intersect: true },
        plugins: {
          legend: { display: false },
          tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: dark ? 'rgba(30,41,59,0.97)' : 'rgba(37,99,235,0.97)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: dark ? '#60a5fa' : '#3b82f6',
            borderWidth: 1.5,
            padding: 16,
            displayColors: false,
            titleFont: { size: 16, weight: 'bold', family: 'Inter, Arial, sans-serif' },
            bodyFont: { size: 15, family: 'Inter, Arial, sans-serif' },
            callbacks: {
              title: function(tooltipItems) { return 'Data: ' + tooltipItems[0].label; },
              label: function(context) {
                const valor = context.parsed.y;
                const anterior = context.dataset.data[context.dataIndex - 1] || 0;
                const variacao = getPercentChange(valor, anterior);
                return `Candidatos: ${valor} (${variacao}% vs anterior)`;
              }
            }
          },
          annotation: {
            annotations: {
              metaLine: {
                type: 'line',
                yMin: META_CANDIDATOS_DIA,
                yMax: META_CANDIDATOS_DIA,
                borderColor: '#facc15',
                borderWidth: 2,
                borderDash: [6, 6],
                label: {
                  content: 'Meta',
                  enabled: true,
                  position: 'end',
                  color: '#facc15',
                  font: { weight: 'bold' }
                }
              }
            }
          },
          zoom: {
            pan: {
              enabled: true,
              mode: 'x',
              modifierKey: 'ctrl',
            },
            zoom: {
              wheel: { enabled: true },
              pinch: { enabled: true },
              mode: 'x',
              drag: { enabled: true },
            },
            limits: {
              x: { minRange: 3 }
            }
          }
        },
        onClick: (e, elements) => {
          if (elements && elements.length > 0) {
            const idx = elements[0].index;
            const item = data[idx];
            mostrarGraficoPorHora(item.data);
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              maxRotation: 45,
              minRotation: 45,
              font: { size: 13, family: 'Inter, Arial, sans-serif', weight: 500 },
              color: dark ? '#cbd5e1' : '#334155',
              callback: function(value) { return this.getLabelForValue(value); }
            }
          },
          y: {
            beginAtZero: true,
            grid: { color: dark ? 'rgba(255,255,255,0.04)' : 'rgba(0, 0, 0, 0.04)', drawBorder: false },
            border: { display: false },
            ticks: {
              stepSize: 1,
              font: { size: 13, family: 'Inter, Arial, sans-serif', weight: 500 },
              color: dark ? '#cbd5e1' : '#334155',
              padding: 8,
              callback: function(value) { return value.toFixed(0); }
            }
          }
        }
      }
    });
  } catch (error) {
    if (candidatesChart) {
      candidatesChart.destroy();
      candidatesChart = null;
    }
    const canvas = document.getElementById('candidatesByDayChart');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.font = '14px Arial';
    ctx.fillStyle = '#EF4444';
    ctx.textAlign = 'center';
    ctx.fillText('Erro ao carregar o gráfico:', canvas.width/2, canvas.height/2 - 10);
    ctx.fillText(error.message || 'Por favor, tente novamente.', canvas.width/2, canvas.height/2 + 10);
  }
}

// Função para carregar estatísticas
async function loadStats(dataInicio = null, dataFim = null) {
  try {
    document.getElementById('totalCurriculos').textContent = 'Carregando...';
    document.getElementById('curriculosHoje').textContent = 'Carregando...';
    document.getElementById('vagasAbertas').textContent = 'Carregando...';
    document.getElementById('vagasFechadasTotal').textContent = 'Carregando...';
    let url = `${getApiBaseUrl()}/get_stats.php`;
    if (dataInicio && dataFim) url += `?data_inicio=${dataInicio}&data_fim=${dataFim}`;
    const response = await fetch(url);
    if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);
    const data = await response.json();
    if (!data || !data.stats || !data.stats_vagas) throw new Error('Dados inválidos recebidos do servidor');
    animateCount('totalCurriculos', parseInt(data.stats.total_curriculos) || 0);
    animateCount('curriculosHoje', parseInt(data.stats.curriculos_hoje) || 0);
    animateCount('vagasAbertas', parseInt(data.stats_vagas.vagas_abertas) || 0);
    document.getElementById('posicoesAbertas').textContent = `${data.stats_vagas.total_posicoes_abertas || '0'} posições disponíveis`;
    animateCount('vagasFechadasTotal', parseInt(data.stats_vagas.vagas_fechadas) || 0);
    document.getElementById('mediaFechamento').textContent = `Média de ${Math.round(data.media_fechamento?.media_dias_fechamento || 0)} dias para fechamento`;
    // Gráfico de status das vagas
    try {
      const ctxStatus = document.getElementById('statusVagasChart');
      if (window.statusVagasChart && typeof window.statusVagasChart === 'object') {
        try { window.statusVagasChart.destroy(); } catch (e) {}
      }
      window.statusVagasChart = null;
      const statusData = {
        labels: ['Em Divulgação', 'Suspensa', 'Aguardando Retorno', 'Fechada'],
        datasets: [{
          data: [
            parseInt(data.stats_vagas.vagas_abertas) || 0,
            parseInt(data.stats_vagas.vagas_suspensas) || 0,
            parseInt(data.stats_vagas.vagas_aguardando) || 0,
            parseInt(data.stats_vagas.vagas_fechadas) || 0
          ],
          backgroundColor: [
            'linear-gradient(135deg, #facc15 60%, #fde68a 100%)',
            'linear-gradient(135deg, #ef4444 60%, #fca5a5 100%)',
            'linear-gradient(135deg, #64748b 60%, #cbd5e1 100%)',
            'linear-gradient(135deg, #22c55e 60%, #bbf7d0 100%)'
          ].map((g, i) => {
            // fallback para navegadores que não suportam gradiente em Chart.js
            const ctx = ctxStatus.getContext('2d');
            const grad = ctx.createLinearGradient(0, 0, 0, 200);
            if (i === 0) { grad.addColorStop(0, '#facc15'); grad.addColorStop(1, '#fde68a'); }
            if (i === 1) { grad.addColorStop(0, '#ef4444'); grad.addColorStop(1, '#fca5a5'); }
            if (i === 2) { grad.addColorStop(0, '#64748b'); grad.addColorStop(1, '#cbd5e1'); }
            if (i === 3) { grad.addColorStop(0, '#22c55e'); grad.addColorStop(1, '#bbf7d0'); }
            return grad;
          }),
          borderWidth: 2,
          borderColor: '#fff',
          hoverOffset: 10
        }]
      };
      window.statusVagasChart = new Chart(ctxStatus, {
        type: 'doughnut',
        data: statusData,
        options: {
          responsive: true,
          maintainAspectRatio: true,
          cutout: '68%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                font: { size: 15, family: 'Inter, Arial, sans-serif', weight: 600 },
                color: '#334155',
                padding: 18
              }
            },
            tooltip: {
              backgroundColor: '#2563eb',
              titleColor: '#fff',
              bodyColor: '#fff',
              borderColor: '#1e293b',
              borderWidth: 1.5,
              padding: 14,
              displayColors: false,
              titleFont: { size: 15, weight: 'bold' },
              bodyFont: { size: 14 },
              callbacks: {
                title: items => items[0].label,
                label: ctx => `Total: ${ctx.parsed}`
              }
            }
          },
          animation: {
            animateRotate: true,
            animateScale: true,
            duration: 1200,
            easing: 'easeOutQuart'
          }
        }
      });
    } catch (chartError) {
      const statusContainer = document.getElementById('statusVagasChart');
      if (statusContainer) {
        statusContainer.innerHTML = `<p class="text-red-600 p-4">Erro ao carregar gráfico: ${chartError.message}</p>`;
      }
    }
    // Gráfico de evolução
    try {
      const ctxEvolucao = document.getElementById('evolucaoVagasChart');
      if (window.evolucaoVagasChart && typeof window.evolucaoVagasChart === 'object') {
        try { window.evolucaoVagasChart.destroy(); } catch (e) {}
      }
      window.evolucaoVagasChart = null;
      if (!Array.isArray(data.vagas_por_mes)) throw new Error('Dados de evolução inválidos');
      const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
      const evolucaoData = {
        labels: data.vagas_por_mes.map(item => {
          const [ano, mes] = (item.mes || '').split('-');
          return mes && ano ? `${meses[parseInt(mes) - 1]}/${ano.slice(2)}` : '';
        }).filter(Boolean),
        datasets: [
          {
            label: 'Total de Vagas',
            data: data.vagas_por_mes.map(item => parseInt(item.total_vagas) || 0),
            borderColor: '#2563eb',
            backgroundColor: '#2563eb20',
            fill: true,
            tension: 0.4
          },
          {
            label: 'Vagas Fechadas',
            data: data.vagas_por_mes.map(item => parseInt(item.vagas_fechadas) || 0),
            borderColor: '#22c55e',
            backgroundColor: '#22c55e20',
            fill: true,
            tension: 0.4
          }
        ]
      };
      window.evolucaoVagasChart = new Chart(ctxEvolucao, {
        type: 'line',
        data: evolucaoData,
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                font: { size: 15, family: 'Inter, Arial, sans-serif', weight: 600 },
                color: '#334155',
                padding: 18
              }
            },
            tooltip: {
              mode: 'index',
              intersect: false,
              backgroundColor: 'rgba(37,99,235,0.97)',
              titleColor: '#fff',
              bodyColor: '#fff',
              borderColor: '#3b82f6',
              borderWidth: 1.5,
              padding: 16,
              displayColors: true,
              titleFont: { size: 16, weight: 'bold', family: 'Inter, Arial, sans-serif' },
              bodyFont: { size: 15, family: 'Inter, Arial, sans-serif' }
            }
          },
          animation: {
            duration: 1200,
            easing: 'easeOutQuart'
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(0, 0, 0, 0.04)', drawBorder: false },
              border: { display: false },
              ticks: {
                font: { size: 13, family: 'Inter, Arial, sans-serif', weight: 500 },
                color: '#334155',
                padding: 8
              }
            },
            x: {
              grid: { display: false },
              ticks: {
                font: { size: 13, family: 'Inter, Arial, sans-serif', weight: 500 },
                color: '#334155',
                padding: 8
              }
            }
          },
          elements: {
            line: { borderWidth: 3, tension: 0.45 },
            point: {
              radius: 5,
              backgroundColor: ctx => ctx.datasetIndex === 0 ? '#2563eb' : '#22c55e',
              borderColor: '#fff',
              borderWidth: 2.5,
              hoverRadius: 8,
              hoverBackgroundColor: ctx => ctx.datasetIndex === 0 ? '#1e40af' : '#16a34a',
              hoverBorderColor: '#fff',
              hoverBorderWidth: 2.5
            }
          }
        }
      });
    } catch (chartError) {
      const evolucaoContainer = document.getElementById('evolucaoVagasChart');
      if (evolucaoContainer) {
        evolucaoContainer.innerHTML = `<p class="text-red-600 p-4">Erro ao carregar gráfico: ${chartError.message}</p>`;
      }
    }
    // Áreas de interesse
    const areasContainer = document.getElementById('areasInteresse');
    if (areasContainer) {
      const maxTotal = data.areas_interesse && data.areas_interesse.length > 0 ? Math.max(...data.areas_interesse.map(a => a.total || 0)) : 1;
      areasContainer.innerHTML = data.areas_interesse && Array.isArray(data.areas_interesse)
        ? data.areas_interesse.map(area => {
            const total = area.total || 0;
            const percent = ((total / (data.stats.curriculos_periodo || 1)) * 100).toFixed(1);
            const barraPercent = ((total / maxTotal) * 100).toFixed(1);
            return `
              <div class="bg-gray-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
                  <span class="font-medium truncate" style="max-width:60%">${area.area_interesse || 'Não especificada'}</span>
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-xs text-gray-600" style="min-width:38px;text-align:right;">${percent}%</span>
                    <span class="bg-accent text-white px-3 py-1 rounded-full text-sm">${total}</span>
                  </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div class="bg-accent h-2 rounded-full" style="width: ${barraPercent}%;"></div>
                </div>
              </div>
            `;
          }).join('')
        : '<p class="text-gray-600">Nenhuma área de interesse registrada.</p>';
    }
    // Áreas com mais vagas
    const areasVagasContainer = document.getElementById('areasVagas');
    if (areasVagasContainer) {
      areasVagasContainer.innerHTML = data.top_areas_vagas && Array.isArray(data.top_areas_vagas)
        ? data.top_areas_vagas.map(area => `
            <div class="bg-gray-50 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="font-medium text-navy">${area.area_principal || 'Não especificada'}</span>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-gray-600">${((area.total / data.stats_vagas.total_vagas) * 100).toFixed(1)}%</span>
                  <span class="bg-yellow-500 text-white px-3 py-1 rounded-full text-sm">${area.total || 0}</span>
                </div>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-yellow-500 h-2.5 rounded-full transition-all duration-500" style="width: ${((area.total || 0) / (data.stats_vagas.total_vagas || 1) * 100)}%"></div>
              </div>
            </div>
          `).join('')
        : '<p class="text-gray-600">Nenhuma área com vagas registrada.</p>';
    }
    // Vagas fechadas
    const vagasFechadasContainer = document.getElementById('vagasFechadas');
    if (vagasFechadasContainer) {
      vagasFechadasContainer.innerHTML = data.vagas_fechadas && Array.isArray(data.vagas_fechadas) && data.vagas_fechadas.length > 0
        ? data.vagas_fechadas.map(vaga => `
            <div class="bg-gray-50 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <div>
                  <h4 class="font-medium text-navy">${vaga.title || 'Sem título'}</h4>
                  <p class="text-sm text-gray-600">${vaga.company || 'Empresa não especificada'}</p>
                </div>
                ${vaga.candidato ? `
                  <button class="view-resume-details text-accent hover:underline text-sm" data-email="${vaga.candidato}">Ver Candidato</button>
                ` : ''}
              </div>
              <div class="text-sm text-gray-600">
                <p>Data de Fechamento: ${vaga.date_fechamento ? new Date(vaga.date_fechamento).toLocaleDateString('pt-BR') : 'Não especificada'}</p>
              </div>
            </div>
          `).join('')
        : '<p class="text-gray-600">Nenhuma vaga fechada no período.</p>';
      document.querySelectorAll('.view-resume-details').forEach(button => {
        button.addEventListener('click', (e) => {
          // Aqui você pode implementar a lógica para exibir detalhes do candidato
          alert('Funcionalidade de ver candidato não implementada nesta página.');
        });
      });
    }
  } catch (error) {
    const elements = {
      'totalCurriculos': 'Erro',
      'curriculosHoje': 'Erro',
      'vagasAbertas': 'Erro',
      'vagasFechadasTotal': 'Erro'
    };
    for (const [id, text] of Object.entries(elements)) {
      const element = document.getElementById(id);
      if (element) element.textContent = text;
    }
    const containers = ['areasInteresse', 'areasVagas', 'vagasFechadas'];
    containers.forEach(id => {
      const container = document.getElementById(id);
      if (container) container.innerHTML = `<p class="text-red-600">Erro ao carregar dados: ${error.message}</p>`;
    });
  }
}

async function mostrarGraficoPorHora(dataSelecionada) {
  // Exibe modal
  document.getElementById('modalPorHora').style.display = 'flex';
  document.getElementById('modalHoraTitulo').textContent = `Candidatos por Hora em ${dataSelecionada}`;
  // Limpa gráfico anterior
  if (graficoPorHora) { graficoPorHora.destroy(); graficoPorHora = null; }
  // Busca dados
  try {
    const resp = await fetch(`${getApiBaseUrl()}/get_candidates_by_hour.php?data=${dataSelecionada}`);
    const dados = await resp.json();
    if (!Array.isArray(dados)) throw new Error('Dados inválidos');
    const totalDia = dados.reduce((soma, d) => soma + d.quantidade, 0);
    const ctx = document.getElementById('graficoPorHora').getContext('2d');
    if (totalDia === 0) {
      ctx.clearRect(0,0,380,220);
      ctx.font = '16px Arial';
      ctx.fillStyle = '#64748b';
      ctx.textAlign = 'center';
      ctx.fillText('Sem dados para este dia', 190, 110);
      return;
    }
    graficoPorHora = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: dados.map(d => d.hora + 'h'),
        datasets: [{
          label: 'Candidatos',
          data: dados.map(d => d.quantidade),
          backgroundColor: function(context) {
            const grad = ctx.createLinearGradient(0, 0, 0, 220);
            grad.addColorStop(0, '#2563eb');
            grad.addColorStop(1, '#60a5fa');
            return grad;
          },
          borderRadius: 12,
          maxBarThickness: 32,
          borderSkipped: false,
          shadowOffsetX: 0,
          shadowOffsetY: 2,
          shadowBlur: 8,
          shadowColor: 'rgba(37,99,235,0.18)'
        }]
      },
      options: {
        responsive: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2563eb',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: '#1e293b',
            borderWidth: 1.5,
            padding: 14,
            displayColors: false,
            titleFont: { size: 15, weight: 'bold' },
            bodyFont: { size: 14 },
            callbacks: {
              title: items => `Hora: ${items[0].label}`,
              label: ctx => `Candidatos: ${ctx.parsed.y}`
            }
          }
        },
        animation: {
          duration: 1000,
          easing: 'easeOutQuart'
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 13, family: 'Inter, Arial, sans-serif', weight: 500 }, color: '#334155' } },
          y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { font: { size: 13, family: 'Inter, Arial, sans-serif', weight: 500 }, color: '#334155' } }
        }
      }
    });
  } catch (e) {
    const ctx = document.getElementById('graficoPorHora').getContext('2d');
    ctx.clearRect(0,0,380,220);
    ctx.font = '15px Arial';
    ctx.fillStyle = '#e11d48';
    ctx.textAlign = 'center';
    ctx.fillText('Erro ao carregar dados', 190, 110);
  }
}

document.getElementById('btnFecharModalHora').onclick = function() {
  document.getElementById('modalPorHora').style.display = 'none';
  if (graficoPorHora) { graficoPorHora.destroy(); graficoPorHora = null; }
};

// Inicialização ao carregar a página
window.addEventListener('DOMContentLoaded', function() {
  updateDashboard();
  // Botão de filtro
  const statsFilter = document.getElementById('statsFilter');
  if (statsFilter) {
    statsFilter.addEventListener('click', updateDashboard);
  }
});

// Animação de contagem nos cards de estatísticas
function animateCount(id, end) {
  const el = document.getElementById(id);
  if (!el) return;
  let start = 0;
  const duration = 900;
  const step = Math.ceil(end / (duration / 16));
  function update() {
    start += step;
    if (start >= end) {
      el.textContent = end;
    } else {
      el.textContent = start;
      requestAnimationFrame(update);
    }
  }
  update();
}

// Função utilitária para detectar modo escuro
function isDarkMode() {
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

// Função para calcular variação percentual
function getPercentChange(current, previous) {
  if (!previous || previous === 0) return '0';
  return ((current - previous) / previous * 100).toFixed(1);
}

// Exemplo de meta para candidatos por dia
const META_CANDIDATOS_DIA = 20; 