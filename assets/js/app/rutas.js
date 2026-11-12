// Función 6 · Rutas seguras: el usuario marca dónde está y el backend (lib/rutas.php)
// devuelve la ruta al punto seguro disponible más conveniente, evitando zonas en riesgo.

App.registrar('rutas', {
  mapa: null, capaPuntos: null, capaRuta: null, origen: null,

  iniciar() {
    document.getElementById('botonUbicacion').addEventListener('click', () => this.usarUbicacion());
  },

  alMostrar() {
    if (!this.mapa) {
      this.mapa = App.crearMapa('mapaRutas');
      this.capaPuntos = L.layerGroup().addTo(this.mapa);
      this.capaRuta = L.layerGroup().addTo(this.mapa);
      this.mapa.on('click', e => this.calcular(e.latlng.lat, e.latlng.lng));
    }
    setTimeout(() => this.mapa.invalidateSize(), 50);
  },

  // Puntos seguros: disponibles en verde; ocupados o cerrados en gris.
  pintar() {
    if (!this.mapa) return;
    this.capaPuntos.clearLayers();
    App.datos.puntos.forEach(p => {
      const disponible = p.estado === 'disponible';
      const color = disponible ? '#16a34a' : '#71717a';
      L.circleMarker([p.lat, p.lng], { radius: 8, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 })
        .addTo(this.capaPuntos)
        .bindTooltip(p.nombre + ' · ' + p.estado);
    });
  },

  usarUbicacion() {
    const estado = document.getElementById('estadoUbicacion');
    if (!navigator.geolocation) { estado.textContent = 'Este navegador no da la ubicación. Toca el mapa donde estás.'; return; }
    estado.textContent = 'Buscando tu ubicación…';
    navigator.geolocation.getCurrentPosition(
      pos => { estado.textContent = ''; this.calcular(pos.coords.latitude, pos.coords.longitude); },
      () => { estado.textContent = 'No se pudo obtener tu ubicación. Toca el mapa donde estás.'; },
      { timeout: 10000 }
    );
  },

  async calcular(lat, lng) {
    const detalle = document.getElementById('detalleRuta');
    detalle.replaceChildren(App.crear('p', 'Calculando ruta…', 'text-sm mb-0'));
    try {
      const r = await App.pedir(App.url('ruta.php', { lat: lat.toFixed(6), lng: lng.toFixed(6) }));
      this.dibujar(lat, lng, r);
    } catch (error) {
      this.capaRuta.clearLayers();
      detalle.replaceChildren(App.crear('p', error.message, 'text-sm text-danger mb-0'));
    }
  },

  dibujar(lat, lng, respuesta) {
    const ruta = respuesta.ruta;
    this.capaRuta.clearLayers();

    // Zonas a evitar
    respuesta.zonas.forEach(z => {
      const color = NIVELES[z.nivel].color;
      L.circle([z.lat, z.lng], { radius: z.radio, color, fillColor: color, fillOpacity: 0.25, weight: 1 }).addTo(this.capaRuta).bindTooltip(z.nombre);
    });

    // OSRM entrega [lng, lat]; Leaflet usa [lat, lng].
    const puntos = ruta.coordenadas.map(p => [p[1], p[0]]);
    const linea = L.polyline(puntos, { color: '#0ea5e9', weight: 5, dashArray: ruta.tipo_ruta === 'linea_recta' ? '8 10' : null }).addTo(this.capaRuta);
    L.marker([lat, lng]).addTo(this.capaRuta).bindTooltip('Tu punto de salida');
    L.marker([ruta.destino.lat, ruta.destino.lng]).addTo(this.capaRuta).bindTooltip(ruta.destino.nombre);
    this.mapa.fitBounds(linea.getBounds(), { padding: [30, 30] });

    const detalle = document.getElementById('detalleRuta');
    detalle.replaceChildren();
    detalle.append(App.crear('p', 'Destino', 'text-xs text-uppercase text-secondary mb-0'));
    detalle.append(App.crear('h6', ruta.destino.nombre, 'mb-1'));
    detalle.append(App.crear('p', ruta.destino.direccion, 'text-sm mb-1'));
    detalle.append(App.crear('p', 'Capacidad: ' + ruta.destino.capacidad + ' personas' + (ruta.destino.telefono ? ' · Tel. ' + ruta.destino.telefono : ''), 'text-sm mb-2'));
    const km = (ruta.distancia_m / 1000).toFixed(1);
    detalle.append(App.crear('p', km + ' km · unos ' + ruta.minutos_a_pie + ' minutos a pie', 'text-sm font-weight-bold mb-2'));

    const aviso = (texto, clase) => detalle.append(App.crear('p', texto, 'text-sm mb-2 ' + clase));
    if (ruta.tipo_ruta === 'linea_recta') aviso('No se pudo consultar el servicio de rutas: se muestra una línea recta, no un camino por calles.', 'text-warning');
    if (ruta.sale_de_zona_de_riesgo.length) aviso('Estás dentro de una zona en riesgo: ' + ruta.sale_de_zona_de_riesgo.join(', ') + '.', 'text-danger');
    if (ruta.cruza_zonas_rojas.length) aviso('No hay ruta que evite todas las zonas de riesgo alto. Esta pasa por: ' + ruta.cruza_zonas_rojas.join(', ') + '.', 'text-danger');
    else aviso('La ruta no pasa por zonas de riesgo alto.', 'text-success');
    if (ruta.cruza_zonas_amarillas.length) aviso('Pasa por zonas de riesgo medio: ' + ruta.cruza_zonas_amarillas.join(', ') + '.', 'text-secondary');
    if (ruta.destino.en_zona_roja) aviso('El punto seguro está dentro de una zona de riesgo alto; no hay otro disponible más conveniente.', 'text-danger');
    if (respuesta.simulacion) aviso('Calculada con lluvia simulada.', 'text-secondary');
    detalle.append(App.crear('p', 'Las zonas son aproximadas (círculos alrededor de cada colonia y de los reportes). Sigue siempre las indicaciones de Protección Civil.', 'text-xs text-secondary mb-0'));
  }
});
