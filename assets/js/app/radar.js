// Función 1 · Radar: mapa de colonias por nivel de riesgo y formulario de reportes.

App.registrar('radar', {
  mapa: null, capa: null, seleccionada: null,
  marcando: false, ubicacion: null, marcador: null,

  iniciar() {
    this.mapa = App.crearMapa('mapaRadar');
    this.capa = L.layerGroup().addTo(this.mapa);

    // Marcar el lugar del reporte con un toque en el mapa.
    document.getElementById('botonMarcar').addEventListener('click', () => {
      this.marcando = true;
      document.getElementById('mapaRadar').classList.add('marcando');
      document.getElementById('textoUbicacion').textContent = 'Toca el mapa en el lugar del problema.';
    });
    this.mapa.on('click', e => {
      if (!this.marcando) return;
      this.marcando = false;
      document.getElementById('mapaRadar').classList.remove('marcando');
      this.ubicacion = { lat: e.latlng.lat, lng: e.latlng.lng };
      if (this.marcador) this.marcador.remove();
      this.marcador = L.marker(e.latlng).addTo(this.mapa).bindTooltip('Lugar del reporte');
      document.getElementById('textoUbicacion').textContent = 'Lugar marcado en el mapa.';
    });

    document.getElementById('formularioReporte').addEventListener('submit', e => this.enviar(e));
    // Si el tipo de cuenta solo puede consultar, el formulario de reportes no se muestra.
    document.getElementById('formularioReporte').closest('.card').classList.toggle('d-none', !App.puedeReportar);
  },

  alMostrar() { setTimeout(() => this.mapa.invalidateSize(), 50); },

  pintar() {
    // Lista de colonias del formulario (se conserva la que ya estaba elegida).
    const selector = document.getElementById('coloniaReporte');
    const elegida = selector.value;
    selector.replaceChildren();
    const automatica = App.crear('option', 'Detectar por el texto o el mapa');
    automatica.value = '';
    selector.append(automatica);
    App.datos.colonias.forEach(c => {
      const opcion = App.crear('option', c.nombre);
      opcion.value = c.id;
      selector.append(opcion);
    });
    selector.value = elegida;

    this.capa.clearLayers();
    App.datos.colonias.forEach(c => {
      const color = NIVELES[c.nivel].color;
      const circulo = L.circle([c.lat, c.lng], { radius: 600, color, fillColor: color, fillOpacity: 0.45, weight: 2 }).addTo(this.capa);
      circulo.bindTooltip(c.nombre + ' · ' + NIVELES[c.nivel].nombre);
      circulo.on('click', () => {
        if (this.marcando) return; // ese toque es para marcar el lugar del reporte
        this.seleccionada = c.id;
        this.detalle(c);
      });
      if (c.id === this.seleccionada) this.detalle(c);
    });
  },

  detalle(c) {
    const caja = document.getElementById('detalleColonia');
    caja.replaceChildren();
    caja.append(App.crear('h5', c.nombre, 'mb-1'));
    const linea = App.crear('p', undefined, 'mb-2');
    linea.append(App.etiquetaNivel(c.nivel), App.crear('span', ' ' + c.puntaje + ' de 100', 'text-sm font-weight-bold'));
    caja.append(linea);
    const lluvia = c.lluvia_mm_6h === null ? 'sin dato' : c.lluvia_mm_6h + ' mm';
    caja.append(App.crear('p', 'Lluvia esperada en 6 horas: ' + lluvia, 'text-sm mb-1'));
    caja.append(App.crear('p', 'Reportes de inundación (últimas 3 h): ' + c.reportes_recientes, 'text-sm mb-1'));
    caja.append(App.crear('p', 'Reportes anteriores de inundación: ' + c.antecedentes, 'text-sm mb-2'));
    caja.append(App.crear('p', 'Qué hacer', 'text-sm font-weight-bold mb-1'));
    const lista = App.crear('ul', undefined, 'text-sm ps-3 mb-0');
    c.guia.forEach(paso => lista.append(App.crear('li', paso)));
    caja.append(lista);
  },

  async enviar(evento) {
    evento.preventDefault();
    const campo = document.getElementById('textoReporte');
    const boton = document.getElementById('botonEnviar');
    const resultado = document.getElementById('resultadoReporte');
    boton.disabled = true;
    resultado.textContent = 'Clasificando…';
    try {
      const cuerpo = { texto: campo.value };
      const colonia = document.getElementById('coloniaReporte').value;
      if (colonia !== '') cuerpo.colonia_id = Number(colonia);
      if (this.ubicacion) { cuerpo.lat = this.ubicacion.lat; cuerpo.lng = this.ubicacion.lng; }
      const r = await App.pedir('api/reportes.php', cuerpo);
      resultado.textContent = 'Reporte guardado: ' + r.titulo + ', urgencia ' + NOMBRE_URGENCIA[r.urgencia].toLowerCase()
        + '. Clasificado con: ' + NOMBRE_METODO[r.metodo] + '.';
      campo.value = '';
      this.ubicacion = null;
      if (this.marcador) { this.marcador.remove(); this.marcador = null; }
      document.getElementById('textoUbicacion').textContent = 'Sin lugar marcado en el mapa.';
      await App.cargar();
    } catch (error) {
      resultado.textContent = 'No se pudo enviar el reporte: ' + error.message;
    } finally {
      boton.disabled = false;
    }
  }
});
