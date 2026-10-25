(() => {
  const form = document.getElementById('form-reserva');
  if (!form) return;

  const inicio  = document.getElementById('data_inicio');
  const fim     = document.getElementById('data_fim');
  const bloco   = document.getElementById('bloco-fim');
  const rotIni  = document.getElementById('rotulo-inicio');
  const rotFim  = document.getElementById('rotulo-fim');
  const pessoas = document.getElementById('num_pessoas');
  const rServ   = document.getElementById('resumo-servico');
  const rDatas  = document.getElementById('resumo-datas');
  const rDur    = document.getElementById('resumo-duracao');
  const rTotal  = document.getElementById('resumo-total');

  const TEXTOS = {
    noite:  { ini: 'Check-in',       fim: 'Check-out' },
    dia:    { ini: 'Levantamento',   fim: 'Devolução' },
    evento: { ini: 'Data do evento', fim: '' },
  };
  const UNIDADES = { noite: 'noite(s)', dia: 'dia(s)', evento: 'evento' };

  const MS_DIA   = 86400000;
  const utc      = (s) => { const [a, m, d] = s.split('-').map(Number); return Date.UTC(a, m - 1, d); };
  const maisDias = (s, n) => new Date(utc(s) + n * MS_DIA).toISOString().slice(0, 10);
  const br       = (s) => s.split('-').reverse().join('/');
  const moeda    = (v) => v.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MT';

  function atualizar() {
    const sel = form.querySelector('input[name="servico_id"]:checked');
    if (!sel) return;

    const un     = sel.dataset.unidade;
    const evento = un === 'evento';
    const t      = TEXTOS[un] || TEXTOS.evento;
    const preco  = parseFloat(sel.dataset.preco) || 0;

    rotIni.textContent = t.ini;
    rotFim.textContent = t.fim;
    bloco.classList.toggle('hidden', evento);
    fim.disabled = evento;          // um campo desativado não é enviado: o servidor usa a data de início
    fim.required = !evento;
    pessoas.max  = sel.dataset.capacidade;

    if (inicio.value) fim.min = un === 'noite' ? maisDias(inicio.value, 1) : inicio.value;

    // Mesmas regras do servidor: quarto = noites, viatura = dias com o da devolução, evento = 1
    let unidades = null;
    if (evento) {
      unidades = inicio.value ? 1 : null;
    } else if (inicio.value && fim.value) {
      const dias = Math.round((utc(fim.value) - utc(inicio.value)) / MS_DIA);
      if (un === 'noite' && dias >= 1) unidades = dias;
      if (un === 'dia' && dias >= 0)   unidades = dias + 1;
    }

    rServ.textContent  = sel.dataset.nome;
    rDatas.textContent = !inicio.value ? '—'
      : (evento || !fim.value ? br(inicio.value) : br(inicio.value) + ' → ' + br(fim.value));
    rDur.textContent   = unidades ? unidades + ' ' + UNIDADES[un] : '—';
    rTotal.textContent = unidades ? moeda(unidades * preco) : '—';
  }

  form.addEventListener('change', atualizar);
  form.addEventListener('input', atualizar);
  atualizar();
})();