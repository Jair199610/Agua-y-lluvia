// Función 4 · Detector colaborativo de fugas: muestra los grupos de reportes
// cercanos entre sí que calcula lib/fugas.php.

const CONFIANZA = {
  alta:    { nombre: 'Confianza alta',  color: '#dc2626', clase: 'bg-gradient-danger' },
  media:   { nombre: 'Confianza media', color: '#eab308', clase: 'bg-gradient-warning' },
  aislado: { nombre: 'Reporte aislado', color: '#71717a', clase: 'bg-gradient-secondary' }
};

App.registrar('fugas', {
  mapa: null, capa: null,

  iniciar() {},

  // El mapa se crea la primera vez que se abre la sección (Leaflet necesita que sea visible).
  alMostrar() {
    if (!this.mapa) {
      this.mapa = App.crearMapa('mapaFugas');
      this.capa = L.layerGroup().addTo(this.mapa);
    }
    setTimeout(() => this.mapa.invalidateSize(), 50);
  },

  pintar() {
    if (!this.mapa) return;
    document.getElementById('radioFuga').textContent = App.datos.radioFuga;
    this.capa.clearLayers();
    const lista = document.getElementById('listaFugas');
    lista.replaceChildren();

    if (!App.datos.fugas.length) {
      lista.append(App.crear('p', 'No hay reportes de fuga en las últimas 24 horas.', 'text-sm text-secondary mb-0'));
      return;
    }

    App.datos.fugas.forEach(f => {
      const c = CONFIANZA[f.confianza];
      // Zona del grupo y un punto por cada reporte.
      L.circle([f.lat, f.lng], { radius: f.confianza === 'aislado' ? 60 : App.datos.radioFuga, color: c.color, fillColor: c.color, fillOpacity: 0.25, weight: 2 })
        .addTo(this.capa)
        .bindTooltip((f.confianza === 'aislado' ? 'Reporte aislado' : 'Fuga probable') + ' · ' + f.total_reportes + ' reportes');

      const tarjeta = App.crear('div', undefined, 'border-bottom py-3');
      const titulo = App.crear('p', undefined, 'mb-1');
      titulo.append(App.crear('span', c.nombre, 'badge badge-sm ' + c.clase),
        App.crear('span', ' ' + (f.colonia || 'Colonia no identificada'), 'text-sm font-weight-bold'));
      tarjeta.append(titulo);
      tarjeta.append(App.crear('p', f.reportes_fuga + ' de fuga y ' + f.reportes_falta_de_agua + ' de falta de agua en la misma zona.', 'text-sm mb-1'));
      const reportes = App.crear('ul', undefined, 'text-xs text-secondary ps-3 mb-0');
      f.reportes.forEach(r => reportes.append(App.crear('li', r.texto + (r.es_demo ? ' (demo)' : ''))));
      tarjeta.append(reportes);
      tarjeta.style.cursor = 'pointer';
      tarjeta.addEventListener('click', () => this.mapa.setView([f.lat, f.lng], 15));
      lista.append(tarjeta);
    });
  }
});
