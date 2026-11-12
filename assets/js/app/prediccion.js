// Función 2 · Predicción: puntaje de riesgo con su desglose, lluvia por hora,
// análisis redactado por IA y simulador de lluvia.
// El puntaje es una fórmula (lib/riesgo.php); la IA redacta la explicación.

App.registrar('prediccion', {
  grafica: null, graficaHistorial: null, coloniaId: null, analisisPedido: null,

  iniciar() {
    document.getElementById('selectorColonia').addEventListener('change', e => {
      this.coloniaId = Number(e.target.value);
      this.pintar();
    });
    const deslizador = document.getElementById('deslizadorLluvia');
    deslizador.addEventListener('input', () => { document.getElementById('valorDeslizador').textContent = deslizador.value; });
    document.getElementById('botonSimular').addEventListener('click', () => App.simular(Number(deslizador.value)));
  },

  pintar() {
    const colonias = App.datos.colonias;
    if (!colonias.length) return;
    const ordenadas = [...colonias].sort((a, b) => b.puntaje - a.puntaje);
    if (this.coloniaId === null) this.coloniaId = ordenadas[0].id;

    // Selector de colonia
    const selector = document.getElementById('selectorColonia');
    selector.replaceChildren();
    colonias.forEach(c => {
      const opcion = App.crear('option', c.nombre);
      opcion.value = c.id;
      opcion.selected = c.id === this.coloniaId;
      selector.append(opcion);
    });

    const c = colonias.find(x => x.id === this.coloniaId) || ordenadas[0];
    this.resumen(c);
    this.dibujarGrafica(c);
    this.analisis(c);
    this.historial(c);

    // Tabla de todas las colonias
    const tabla = document.getElementById('tablaPrediccion');
    tabla.replaceChildren();
    ordenadas.forEach(x => {
      const fila = App.crear('tr');
      const nivel = App.crear('td'); nivel.append(App.etiquetaNivel(x.nivel));
      fila.append(
        App.crear('td', x.nombre, 'text-sm font-weight-bold ps-4'), nivel,
        App.crear('td', x.puntaje + ' / 100', 'text-sm'),
        App.crear('td', x.lluvia_mm_6h === null ? 'Sin dato' : x.lluvia_mm_6h + ' mm', 'text-sm'),
        App.crear('td', String(x.antecedentes), 'text-sm'),
        App.crear('td', String(x.reportes_recientes), 'text-sm')
      );
      tabla.append(fila);
    });
  },

  // Puntaje y cuánto aporta cada factor.
  resumen(c) {
    const caja = document.getElementById('resumenPrediccion');
    caja.replaceChildren();
    const titulo = App.crear('p', undefined, 'mb-3');
    titulo.append(App.crear('span', c.puntaje + ' de 100 ', 'h3 font-weight-bolder'), App.etiquetaNivel(c.nivel));
    caja.append(titulo);
    [['Lluvia pronosticada', c.desglose.lluvia, 50, 'bg-gradient-info'],
     ['Reportes anteriores de inundación', c.desglose.antecedentes, 15, 'bg-gradient-dark'],
     ['Reportes de vecinos', c.desglose.reportes, 60, 'bg-gradient-primary']].forEach(([nombre, puntos, maximo, clase]) => {
      caja.append(App.crear('p', nombre + ': ' + puntos + ' de ' + maximo + ' puntos', 'text-sm mb-1'));
      const barra = App.crear('div', undefined, 'progress barra-desglose mb-3');
      const relleno = App.crear('div', undefined, 'progress-bar barra-desglose ' + clase);
      relleno.style.width = Math.round(puntos / maximo * 100) + '%';
      barra.append(relleno);
      caja.append(barra);
    });
  },

  dibujarGrafica(c) {
    const datos = { labels: c.lluvia_etiquetas, datasets: [{ label: 'mm de lluvia', data: c.lluvia_horas, backgroundColor: '#0ea5e9', borderRadius: 4 }] };
    if (this.grafica) {
      this.grafica.data = datos;
      this.grafica.update();
      return;
    }
    this.grafica = new Chart(document.getElementById('graficaLluvia'), {
      type: 'bar',
      data: datos,
      options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, suggestedMax: 5, title: { display: true, text: 'mm' } } }
      }
    });
  },

  // Historial del pronóstico guardado en la tabla mediciones (permiso "monitoreo").
  async historial(c) {
    if (!App.puede('monitoreo')) return;
    try {
      const r = await App.pedir('api/mediciones.php?colonia_id=' + c.id);
      if (c.id !== this.coloniaId) return; // el usuario ya cambió de colonia
      document.getElementById('textoHistorial').textContent = r.mediciones.length
        ? 'Últimos ' + r.mediciones.length + ' pronósticos guardados para ' + c.nombre + ' (uno cada 10 minutos).'
        : 'Aún no hay pronósticos guardados para ' + c.nombre + '.';
      const datos = {
        labels: r.mediciones.map(m => m.fecha.slice(11, 16)),
        datasets: [{ label: 'mm en 6 h', data: r.mediciones.map(m => m.valor), borderColor: '#0ea5e9', backgroundColor: '#0ea5e9', tension: 0.3, pointRadius: 2 }]
      };
      if (this.graficaHistorial) {
        this.graficaHistorial.data = datos;
        this.graficaHistorial.update();
        return;
      }
      this.graficaHistorial = new Chart(document.getElementById('graficaHistorial'), {
        type: 'line',
        data: datos,
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, suggestedMax: 5, title: { display: true, text: 'mm' } } } }
      });
    } catch (error) {
      document.getElementById('textoHistorial').textContent = 'No se pudo cargar el historial.';
    }
  },

  // Pide la explicación al backend solo cuando cambian los datos de la colonia.
  async analisis(c) {
    const clave = [c.id, c.puntaje, c.lluvia_mm_6h, App.simulada].join('|');
    if (clave === this.analisisPedido) return;
    this.analisisPedido = clave;
    const texto = document.getElementById('textoAnalisis');
    const metodo = document.getElementById('metodoAnalisis');
    texto.textContent = 'Generando análisis…';
    metodo.classList.add('d-none');
    try {
      const r = await App.pedir(App.url('analisis.php', { colonia_id: c.id }));
      if (clave !== this.analisisPedido) return; // el usuario ya cambió de colonia
      texto.textContent = r.texto;
      metodo.textContent = r.metodo === 'ia' ? 'Redactado por IA' : 'Texto de plantilla (sin IA)';
      metodo.classList.remove('d-none');
    } catch (error) {
      texto.textContent = 'No se pudo generar el análisis.';
      this.analisisPedido = null;
    }
  }
});
