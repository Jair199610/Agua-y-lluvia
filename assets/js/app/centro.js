// Función 5 · Centro de protección civil: zonas por prioridad, incidentes con
// cambio de estado y disponibilidad de puntos seguros.
// Solo lo ven los roles que administran reportes (todos menos Ciudadano); el servidor lo vuelve a comprobar.

// Valores ENUM de la base aqua_sentinel.
const ESTADOS_REPORTE = { pendiente: 'Pendiente', en_revision: 'En revisión', atendido: 'Atendido', descartado: 'Descartado' };
const ESTADOS_PUNTO = { disponible: 'Disponible', ocupado: 'Ocupado', cerrado: 'Cerrado' };
const TIPOS_PUNTO = { refugio: 'Refugio', centro_salud: 'Centro de salud', punto_reunion: 'Punto de reunión', otro: 'Otro' };

App.registrar('centro', {
  mapaRegistro: null, marcadorRegistro: null, punto: null,   // punto marcado para registrar colonia o punto seguro

  iniciar() {
    document.getElementById('botonCargarDemo').addEventListener('click', () => this.demo('cargar'));
    document.getElementById('botonBorrarDemo').addEventListener('click', () => this.demo('borrar'));

    // Registrar colonias y puntos seguros: solo las autoridades con ese permiso (nunca el Ciudadano).
    const colonias = App.gestiona('colonias'), puntos = App.gestiona('puntos_seguros');
    document.getElementById('filaRegistros').classList.toggle('d-none', !colonias && !puntos);
    document.getElementById('tarjetaNuevaColonia').classList.toggle('d-none', !colonias);
    document.getElementById('tarjetaNuevoPunto').classList.toggle('d-none', !puntos);
    const valor = id => document.getElementById(id).value;
    document.getElementById('formularioColonia').addEventListener('submit', e => this.registrar(e, 'resultadoColonia', 'api/colonias.php', () => ({
      nombre: valor('coloniaNombre'), municipio: valor('coloniaMunicipio'), nivel_vulnerabilidad: valor('coloniaVulnerabilidad')
    }), 'Colonia registrada.'));
    document.getElementById('formularioPunto').addEventListener('submit', e => this.registrar(e, 'resultadoPunto', 'api/puntos.php', () => ({
      accion: 'crear', nombre: valor('puntoNombre'), direccion: valor('puntoDireccion'), tipo: valor('puntoTipo'),
      capacidad: Number(valor('puntoCapacidad')), id_colonia: Number(valor('puntoColonia')), telefono: valor('puntoTelefono')
    }), 'Punto seguro registrado como disponible.'));
  },

  // El mapa de registro se crea la primera vez que se abre la sección.
  alMostrar() {
    if (document.getElementById('filaRegistros').classList.contains('d-none')) return;
    if (!this.mapaRegistro) {
      this.mapaRegistro = App.crearMapa('mapaRegistro');
      this.mapaRegistro.on('click', e => {
        this.punto = { lat: e.latlng.lat, lng: e.latlng.lng };
        if (this.marcadorRegistro) this.marcadorRegistro.remove();
        this.marcadorRegistro = L.marker(e.latlng).addTo(this.mapaRegistro);
        document.getElementById('textoRegistro').textContent = 'Punto marcado: ' + e.latlng.lat.toFixed(5) + ', ' + e.latlng.lng.toFixed(5);
      });
    }
    setTimeout(() => this.mapaRegistro.invalidateSize(), 50);
  },

  // Envía un formulario de registro junto con el punto marcado en el mapa.
  // leerDatos es una función para tomar lo que el usuario escribió justo al enviar.
  async registrar(evento, idResultado, url, leerDatos, mensaje) {
    evento.preventDefault();
    const resultado = document.getElementById(idResultado);
    if (!this.punto) { resultado.textContent = 'Primero toca el mapa para marcar la ubicación.'; return; }
    try {
      await App.pedir(url, { ...leerDatos(), lat: this.punto.lat, lng: this.punto.lng });
      resultado.textContent = mensaje;
      evento.target.reset();
      await App.cargar();
    } catch (error) {
      resultado.textContent = 'No se pudo registrar: ' + error.message;
    }
  },

  async demo(accion) {
    try {
      await App.pedir('api/demo.php', { accion });
      await App.cargar();
    } catch (error) {
      App.avisar('No se pudo ' + accion + ' la demostración', error.message, 'rojo');
    }
  },

  pintar() {
    // Cada rol ve solo lo que puede administrar.
    const gestionaPuntos = App.gestiona('puntos_seguros');
    document.getElementById('tarjetaPuntos').classList.toggle('d-none', !gestionaPuntos);
    document.getElementById('botonesDemo').classList.toggle('d-none', !App.gestiona('simulaciones'));
    this.prioridad();
    if (gestionaPuntos) this.puntos();
    this.incidentes();

    // Lista de colonias del formulario de punto seguro (se conserva la elegida).
    const selector = document.getElementById('puntoColonia');
    const elegida = selector.value;
    selector.replaceChildren();
    App.datos.colonias.forEach(c => {
      const opcion = App.crear('option', c.nombre);
      opcion.value = c.id;
      selector.append(opcion);
    });
    if (elegida) selector.value = elegida;
  },

  // Lista desplegable que guarda el cambio en el backend y recarga los datos.
  selector(opciones, actual, etiqueta, guardar) {
    const selector = App.crear('select', undefined, 'form-select form-select-sm');
    selector.setAttribute('aria-label', etiqueta);
    Object.entries(opciones).forEach(([valor, nombre]) => {
      const opcion = App.crear('option', nombre);
      opcion.value = valor;
      opcion.selected = valor === actual;
      selector.append(opcion);
    });
    selector.addEventListener('change', async () => {
      try {
        await guardar(selector.value);
        await App.cargar();
      } catch (error) {
        selector.value = actual;
        App.avisar('No se pudo guardar el cambio', error.message, 'rojo');
      }
    });
    const celda = App.crear('td');
    celda.style.minWidth = '140px';
    celda.append(selector);
    return celda;
  },

  // Colonias ordenadas por puntaje: las de arriba se atienden primero.
  prioridad() {
    const tabla = document.getElementById('tablaPrioridad');
    tabla.replaceChildren();
    [...App.datos.colonias].sort((a, b) => b.puntaje - a.puntaje).forEach((c, i) => {
      const fila = App.crear('tr');
      const nivel = App.crear('td');
      nivel.append(App.etiquetaNivel(c.nivel));
      fila.append(App.crear('td', String(i + 1), 'text-sm ps-4'), App.crear('td', c.nombre, 'text-sm font-weight-bold'), nivel,
        App.crear('td', String(c.puntaje), 'text-sm'), App.crear('td', String(c.reportes_recientes), 'text-sm'));
      tabla.append(fila);
    });
  },

  puntos() {
    const tabla = document.getElementById('tablaPuntos');
    tabla.replaceChildren();
    App.datos.puntos.forEach(p => {
      const fila = App.crear('tr');
      const lugar = App.crear('td', undefined, 'ps-4');
      lugar.append(App.crear('p', p.nombre, 'text-sm font-weight-bold mb-0'), App.crear('p', p.direccion + ' · ' + p.colonia, 'text-xs text-secondary mb-0'));
      fila.append(lugar, App.crear('td', TIPOS_PUNTO[p.tipo] || p.tipo, 'text-sm'), App.crear('td', String(p.capacidad), 'text-sm'),
        this.selector(ESTADOS_PUNTO, p.estado, 'Estado de ' + p.nombre, estado => App.pedir('api/puntos.php', { id: p.id, estado })));
      tabla.append(fila);
    });
  },

  incidentes() {
    const tabla = document.getElementById('tablaIncidentes');
    tabla.replaceChildren();
    if (!App.datos.reportes.length) {
      const fila = App.crear('tr');
      const celda = App.crear('td', 'Aún no hay reportes.', 'text-sm text-secondary ps-4');
      celda.colSpan = 6;
      fila.append(celda);
      tabla.append(fila);
      return;
    }
    // Primero los que faltan por atender y, dentro de ellos, los más urgentes.
    const ordenEstado = { pendiente: 0, en_revision: 1, atendido: 2, descartado: 3 };
    const ordenUrgencia = { critico: 0, alto: 1, medio: 2, bajo: 3 };
    [...App.datos.reportes].sort((a, b) => ordenEstado[a.estado] - ordenEstado[b.estado] || ordenUrgencia[a.urgencia] - ordenUrgencia[b.urgencia]).forEach(r => {
      const fila = App.crear('tr');
      const reporte = App.crear('td', undefined, 'texto-reporte');
      reporte.append(App.crear('p', r.titulo, 'text-sm font-weight-bold mb-0'), App.crear('p', r.texto, 'text-sm mb-0'));
      fila.append(
        App.crear('td', r.creado_en.slice(5, 16), 'text-xs ps-4'),
        reporte,
        App.crear('td', NOMBRE_TIPO[r.tipo] || r.tipo, 'text-sm'),
        App.crear('td', NOMBRE_URGENCIA[r.urgencia] || r.urgencia, 'text-sm'),
        App.crear('td', r.colonia, 'text-sm'),
        this.selector(ESTADOS_REPORTE, r.estado, 'Estado del reporte ' + r.id, estado => App.pedir('api/reportes.php', { accion: 'estado', id: r.id, estado }))
      );
      tabla.append(fila);
    });
  }
});
