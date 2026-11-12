// Función 3 · Alerta ciudadana: lista de alertas vigentes, campana de la barra
// superior, notificaciones del navegador y alertas por correo para la colonia del usuario.
// El correo lo manda el servidor (lib/correo.php) cuando se abre una alerta; aquí solo se
// guarda la colonia del usuario y se muestra si el correo está activado. No hay SMS ni WhatsApp.

App.registrar('alertas', {
  // Lo que el usuario eligió se guarda en su navegador. Si no se puede, se sigue sin guardar.
  leer(clave, porDefecto) {
    try { const v = localStorage.getItem(clave); return v === null ? porDefecto : JSON.parse(v); } catch (e) { return porDefecto; }
  },
  guardar(clave, valor) {
    try { localStorage.setItem(clave, JSON.stringify(valor)); } catch (e) { /* sin almacenamiento */ }
  },

  iniciar() {
    this.miColonia = this.leer('miColonia', null);
    this.avisadas = this.leer('alertasAvisadas', []);
    document.getElementById('miColonia').addEventListener('change', e => this.cambiarColonia(e.target.value));
    document.getElementById('botonNotificaciones').addEventListener('click', () => this.pedirPermiso());
    document.getElementById('botonPruebaCorreo').addEventListener('click', () => this.probarCorreo());
    this.pintarPermiso();
    this.cargarCorreo();

    // Solo las autoridades pueden enviar avisos; el ciudadano solo ve y recibe los de su colonia.
    const autoridad = App.gestiona('alertas');
    document.getElementById('tarjetaAviso').classList.toggle('d-none', !autoridad);
    document.getElementById('formularioAviso').addEventListener('submit', e => this.enviarAviso(e));
    document.getElementById('avisoTodas').addEventListener('click', () => {
      document.querySelectorAll('#avisoColonias input').forEach(c => { c.checked = true; });
    });
    document.getElementById('tituloListaAlertas').textContent = autoridad ? 'Alertas vigentes en todas las colonias' : 'Alertas de tu colonia';
    document.getElementById('textoListaAlertas').textContent = autoridad ? '' : 'Solo se muestran y se notifican las alertas de la colonia donde vives.';
    document.getElementById('textoReglaAvisos').textContent = autoridad
      ? 'Como autoridad recibes los avisos de todas las colonias. Solo llegan mientras esta página esté abierta.'
      : 'Avisan cuando hay una alerta en tu colonia. Solo llegan mientras esta página esté abierta.';
  },

  // ---------- Aviso de una autoridad a una o varias colonias ----------

  // Casillas de colonias del formulario (se conservan las que ya estaban marcadas).
  pintarColoniasDelAviso() {
    const caja = document.getElementById('avisoColonias');
    const marcadas = [...caja.querySelectorAll('input:checked')].map(c => c.value);
    caja.replaceChildren();
    App.datos.colonias.forEach(c => {
      const fila = App.crear('div', undefined, 'form-check');
      const casilla = App.crear('input', undefined, 'form-check-input');
      casilla.type = 'checkbox';
      casilla.value = c.id;
      casilla.id = 'avisoColonia' + c.id;
      casilla.checked = marcadas.includes(String(c.id));
      const etiqueta = App.crear('label', c.nombre + ' · ' + NIVELES[c.nivel].nombre.toLowerCase(), 'form-check-label text-sm');
      etiqueta.htmlFor = casilla.id;
      fila.append(casilla, etiqueta);
      caja.append(fila);
    });
  },

  async enviarAviso(evento) {
    evento.preventDefault();
    const valor = id => document.getElementById(id).value;
    const resultado = document.getElementById('resultadoAviso');
    const colonias = [...document.querySelectorAll('#avisoColonias input:checked')].map(c => Number(c.value));
    if (!colonias.length) { resultado.textContent = 'Elige al menos una colonia.'; return; }
    resultado.textContent = 'Enviando…';
    try {
      const r = await App.pedir('api/alertas.php', {
        accion: 'crear', titulo: valor('avisoTitulo'), mensaje: valor('avisoMensaje'), tipo: valor('avisoTipo'),
        nivel: valor('avisoNivel'), horas: Number(valor('avisoHoras')), colonias
      });
      resultado.textContent = 'Aviso enviado a ' + r.colonias + ' colonias. '
        + (r.aviso_correo ? 'Correo no enviado: ' + r.aviso_correo + '.' : 'Correos enviados: ' + r.correos + '.');
      evento.target.reset();
      await App.cargar();
    } catch (error) {
      resultado.textContent = 'No se pudo enviar: ' + error.message;
    }
  },

  async finalizarAviso(ids) {
    try {
      await App.pedir('api/alertas.php', { accion: 'finalizar', ids });
      await App.cargar();
    } catch (error) {
      App.avisar('No se pudo finalizar el aviso', error.message, 'rojo');
    }
  },

  // Junta las filas de un mismo aviso manual (una por colonia) en una sola entrada.
  agrupar(alertas) {
    const grupos = new Map();
    alertas.forEach(a => {
      const clave = a.grupo || a.id;
      if (!grupos.has(clave)) grupos.set(clave, { ...a, clave, colonias: [a.colonia], ids: [...a.ids] });
      else {
        const g = grupos.get(clave);
        g.colonias.push(a.colonia);
        g.ids.push(...a.ids);
      }
    });
    return [...grupos.values()];
  },

  // ---------- Alertas por correo ----------

  correo: null,   // lo que responde api/correo.php: { activado, cuenta_configurada, correo, colonia_id }

  async cargarCorreo() {
    try {
      this.correo = await App.pedir('api/correo.php');
      // Si el correo está activado, la colonia guardada en la base es la que manda.
      if (this.correo.activado) {
        this.miColonia = this.correo.colonia_id;
        this.guardar('miColonia', this.miColonia);
      }
    } catch (error) {
      this.correo = null;
    }
    this.pintarCorreo();
    if (App.seccion === 'alertas') this.pintar();
  },

  async cambiarColonia(valor) {
    this.miColonia = valor === '' ? null : Number(valor);
    this.guardar('miColonia', this.miColonia);
    if (this.correo && this.correo.activado) {
      try {
        await App.pedir('api/correo.php', { accion: 'colonia', id_colonia: this.miColonia || 0 });
        this.correo.colonia_id = this.miColonia;
      } catch (error) {
        document.getElementById('resultadoCorreo').textContent = 'No se pudo guardar tu colonia: ' + error.message;
      }
    }
    this.pintarCorreo();
    this.revisar();
  },

  pintarCorreo() {
    const texto = document.getElementById('estadoCorreo');
    const boton = document.getElementById('botonPruebaCorreo');
    const c = this.correo;
    boton.disabled = !(c && c.activado && c.cuenta_configurada);
    if (!c) texto.textContent = 'No se pudo consultar el estado del correo.';
    else if (!c.activado) texto.textContent = 'Las alertas por correo aún no están activadas en este servidor (falta importar sql/correo.sql).';
    else if (!c.cuenta_configurada) texto.textContent = 'El servidor todavía no tiene una cuenta para enviar correos (falta SMTP_USER en el .env). No se está enviando nada.';
    else if (this.miColonia === null) texto.textContent = 'Elige tu colonia para recibir sus alertas en ' + c.correo + '.';
    else texto.textContent = 'Las alertas de tu colonia se envían a ' + c.correo + '. Para dejar de recibirlas, elige "Sin elegir".';
  },

  async probarCorreo() {
    const resultado = document.getElementById('resultadoCorreo');
    resultado.textContent = 'Enviando…';
    try {
      const r = await App.pedir('api/correo.php', { accion: 'prueba' });
      resultado.textContent = 'Correo de prueba enviado a ' + r.enviado_a + '. Revisa también la carpeta de spam.';
    } catch (error) {
      resultado.textContent = error.message;
    }
  },

  async pedirPermiso() {
    if (!('Notification' in window)) return this.pintarPermiso();
    await Notification.requestPermission();
    this.pintarPermiso();
    if (Notification.permission === 'granted') new Notification('Notificaciones activadas', { body: 'Te avisaremos cuando haya riesgo en tu colonia.' });
  },

  pintarPermiso() {
    const texto = document.getElementById('estadoNotificaciones');
    const boton = document.getElementById('botonNotificaciones');
    if (!('Notification' in window)) { texto.textContent = 'Este navegador no permite notificaciones; verás los avisos dentro de la página.'; boton.disabled = true; return; }
    const estados = {
      granted: 'Notificaciones activadas en este navegador.',
      denied: 'Bloqueaste las notificaciones. Puedes permitirlas en los ajustes del navegador; mientras tanto verás los avisos dentro de la página.',
      default: 'Aún no activas las notificaciones.'
    };
    texto.textContent = estados[Notification.permission];
    boton.disabled = Notification.permission === 'granted';
  },

  // ¿Esta alerta le toca al usuario? Lo decide el servidor (campo para_mi): las de su colonia,
  // con un INNER JOIN entre alertas y usuarios, o todas si es autoridad.
  meInteresa(a) {
    return a.para_mi === true;
  },

  // Aviso a las autoridades cuando un ciudadano manda un reporte.
  //   - Administrador y Protección Civil: reciben todos.
  //   - Operador de Agua: solo fugas, falta de agua y alcantarillas.
  //   - Ciudadano: no recibe avisos de reportes de otras personas.
  // Se recuerda el último reporte visto por cada usuario para no repetir avisos.
  revisarReportes() {
    if (!App.gestiona('reportes')) return;
    const deAgua = ['fuga', 'falta_agua', 'alcantarilla'];
    const pendientes = App.datos.reportes.filter(r => r.estado === 'pendiente'
      && (App.usuario.rol !== 'Operador de Agua' || deAgua.includes(r.tipo)));
    const clave = 'ultimoReporteVisto_' + App.usuario.id;
    const visto = this.leer(clave, null);
    const nuevos = visto === null ? [] : pendientes.filter(r => r.id > visto);

    const notificar = (titulo, mensaje) => {
      App.avisar(titulo, mensaje, 'amarillo');
      if ('Notification' in window && Notification.permission === 'granted') new Notification(titulo, { body: mensaje });
    };
    if (visto === null && pendientes.length) {
      // Primera vez que entra en este navegador: un solo resumen.
      notificar('Hay ' + pendientes.length + ' reportes pendientes', 'Revísalos en el Centro de protección civil.');
    } else if (nuevos.length > 3) {
      notificar(nuevos.length + ' reportes nuevos', 'Revísalos en el Centro de protección civil.');
    } else {
      nuevos.forEach(r => notificar('Reporte nuevo: ' + r.titulo, r.texto));
    }
    const ultimo = App.datos.reportes.reduce((mayor, r) => Math.max(mayor, r.id), visto || 0);
    this.guardar(clave, ultimo);
  },

  // Se llama cada vez que llegan datos nuevos: actualiza la campana y avisa de lo nuevo.
  revisar() {
    this.revisarReportes();
    const alertas = this.agrupar(App.datos.alertas.filter(a => this.meInteresa(a)));
    const contador = document.getElementById('contadorAlertas');
    contador.textContent = alertas.length;
    contador.classList.toggle('d-none', alertas.length === 0);

    const campana = document.getElementById('listaCampana');
    campana.replaceChildren();
    if (!alertas.length) campana.append(App.crear('li', 'Sin alertas por ahora.', 'text-sm text-secondary px-2'));
    alertas.slice(0, 6).forEach(a => {
      const li = App.crear('li', undefined, 'mb-2 px-2');
      li.append(App.crear('span', undefined, 'punto-nivel ' + NIVELES[a.color].clase), App.crear('span', a.titulo, 'text-sm font-weight-bold'));
      campana.append(li);
    });

    // Avisar solo de las alertas que no se han avisado antes (el id cambia si cambia el nivel).
    const vigentes = alertas.map(a => a.clave);
    const nuevas = alertas.filter(a => !this.avisadas.includes(a.clave));
    const prefijo = App.simulada !== null ? '[Simulación] ' : '';
    const notificar = (titulo, mensaje, color) => {
      App.avisar(titulo, mensaje, color);
      if ('Notification' in window && Notification.permission === 'granted') new Notification(titulo, { body: mensaje });
    };
    if (nuevas.length > 3) {
      // Muchas a la vez (le pasa a las autoridades): un solo resumen en lugar de llenar la pantalla.
      const rojas = nuevas.filter(a => a.color === 'rojo').length;
      notificar(prefijo + nuevas.length + ' alertas nuevas', rojas + ' de riesgo alto. Revísalas en Alerta ciudadana.', rojas ? 'rojo' : 'amarillo');
    } else {
      nuevas.forEach(a => notificar(prefijo + a.titulo, a.mensaje, a.color));
    }
    nuevas.forEach(a => this.avisadas.push(a.clave));
    // Se olvidan las que ya no están vigentes, para volver a avisar si regresan.
    this.avisadas = this.avisadas.filter(id => vigentes.includes(id));
    this.guardar('alertasAvisadas', this.avisadas);
  },

  pintar() {
    // Selector de "mi colonia"
    const selector = document.getElementById('miColonia');
    selector.replaceChildren();
    const ninguna = App.crear('option', 'Sin elegir');
    ninguna.value = '';
    selector.append(ninguna);
    App.datos.colonias.forEach(c => {
      const opcion = App.crear('option', c.nombre);
      opcion.value = c.id;
      opcion.selected = c.id === this.miColonia;
      selector.append(opcion);
    });

    if (App.gestiona('alertas')) this.pintarColoniasDelAviso();

    const lista = document.getElementById('listaAlertas');
    lista.replaceChildren();
    const visibles = this.agrupar(App.datos.alertas.filter(a => this.meInteresa(a)));
    if (!visibles.length) {
      const sinColonia = !App.gestiona('alertas') && this.miColonia === null;
      lista.append(App.crear('p', sinColonia ? 'Elige tu colonia para ver y recibir sus alertas.' : 'No hay alertas vigentes.', 'text-sm text-secondary mb-0'));
      return;
    }
    visibles.forEach(a => {
      const fila = App.crear('div', undefined, 'd-flex align-items-start border-bottom py-3');
      const texto = App.crear('div', undefined, 'ms-2 flex-grow-1');
      texto.append(App.crear('p', a.titulo, 'text-sm font-weight-bold mb-0'), App.crear('p', a.mensaje, 'text-sm mb-0'));
      if (a.manual) {
        texto.append(App.crear('p', 'Colonias: ' + a.colonias.join(', ') + (a.autor ? ' · Enviado por ' + a.autor : ''), 'text-xs text-secondary mb-0'));
        if (App.gestiona('alertas')) {
          const boton = App.crear('button', 'Finalizar aviso', 'btn btn-outline-dark btn-sm mt-2 mb-0');
          boton.type = 'button';
          boton.addEventListener('click', () => this.finalizarAviso(a.ids));
          texto.append(boton);
        }
      }
      if (this.miColonia !== null && a.colonia_id === this.miColonia) texto.append(App.crear('span', 'Tu colonia', 'badge badge-sm bg-gradient-dark mt-1'));
      // a.nivel viene de la tabla alertas: informativa, preventiva, urgente o emergencia.
      const etiqueta = a.clase === 'fuga'
        ? App.crear('span', 'Fuga', 'badge badge-sm bg-gradient-info')
        : App.crear('span', a.nivel, 'badge badge-sm text-white ' + NIVELES[a.color].clase);
      fila.append(etiqueta, texto);
      lista.append(fila);
    });
  }
});
