<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Página de Inicio - Verden Core</title>
  <link rel="stylesheet" href="assets/css/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/reservaciones.css">
</head>
<body>
  <nav class="navbar">
    <div class="navbar-container">
      <div class="navbar-logo">
        <img src="assets/img/Logo.png" alt="GrupoVerden Logo">
      </div>
      <div class="navbar-buttons">
        <a href="auth/InicioSesion.php" class="btn btn-primary">Iniciar Sesión</a>
      </div>
    </div>
  </nav>

  <main class="main-content">
    <h1 class="main-title">¡Bienvenido a Verden Core!</h1>
    <p class="main-description">En este portal web encontrarán aquellas solicitudes que manejan todos los departamentos
      de la empresa.</p>
    <p class="main-description">Por favor, inicia sesión para continuar.</p>
    
    <section class="calendario-widget">
        <div class="calendario-widget-header">
            <span class="icon-sala" style="font-size:1.5rem;color:#fff;"><i class="bi bi-calendar-event"></i></span>
            <h2>Sala de Juntas — Reservaciones</h2>
        </div>
        <div class="calendario-card">
            <div id="calendario-index"></div>
            <div class="leyenda-departamentos" id="leyenda-deptos"></div>
        </div>
    </section>
  </main>
  
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
      
      function cerrarPopovers() {
          document.querySelectorAll('.rsj-popover').forEach(el => el.remove());
      }
      
      const calendar = new FullCalendar.Calendar(document.getElementById('calendario-index'), {
          initialView: 'timeGridWeek',
          headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
          slotMinTime: '08:00:00', slotMaxTime: '18:00:00', slotDuration: '00:30:00',
          locale: 'es', buttonText: { today: 'Hoy' },
          allDaySlot: false, nowIndicator: true, height: 'auto', expandRows: true,
          editable: false, selectable: false, eventStartEditable: false, eventDurationEditable: false,
          slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
          eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
          
          events: function(info, successCallback, failureCallback) {
              const inicio = info.startStr.split('T')[0];
              const fin = info.endStr.split('T')[0];
              fetch(`api/reservaciones/obtener_eventos_publico.php?inicio=${inicio}&fin=${fin}`)
                  .then(r => r.json()).then(data => { successCallback(data); generarLeyenda(data); })
                  .catch(e => failureCallback(e));
          },
          
          // Contenido dentro del bloque
          eventContent: function(arg) {
              const props = arg.event.extendedProps;
              const html = document.createElement('div');
              html.className = 'rsj-event-content';
              html.innerHTML = `
                  <div class="rsj-event-time">${arg.timeText || ''}</div>
                  <div class="rsj-event-name">${props.solicitante} - ${props.departamento}</div>
                  <div class="rsj-event-lugar">${props.lugar_corto || props.lugar}</div>
                  ${props.motivo ? '<div class="rsj-event-motivo">' + props.motivo + '</div>' : ''}
              `;
              return { domNodes: [html] };
          },
          
          // Click en evento → popover (solo lectura, sin editar/cancelar)
          eventClick: function(info) {
              info.jsEvent.preventDefault();
              info.jsEvent.stopPropagation();
              cerrarPopovers();
              
              const props = info.event.extendedProps;
              
              const popover = document.createElement('div');
              popover.className = 'rsj-popover shadow';
              popover.innerHTML = `
                  <div class="rsj-popover-header" style="background-color:${info.event.backgroundColor}; color:${info.event.textColor}">
                      <strong>${props.solicitante}</strong>
                      <button class="rsj-popover-close">&times;</button>
                  </div>
                  <div class="rsj-popover-body">
                      <div><i class="bi bi-building me-1"></i> ${props.departamento}</div>
                      <div><i class="bi bi-geo-alt me-1"></i> ${props.lugar}</div>
                      <div><i class="bi bi-clock me-1"></i> ${props.hora_inicio} - ${props.hora_fin}</div>
                      ${props.motivo ? '<div><i class="bi bi-chat-text me-1"></i> ' + props.motivo + '</div>' : ''}
                  </div>`;
              document.body.appendChild(popover);
              
              const rect = info.el.getBoundingClientRect();
              popover.style.top = (rect.top + window.scrollY - 10) + 'px';
              popover.style.left = (rect.right + 10) + 'px';
              const popRect = popover.getBoundingClientRect();
              if (popRect.right > window.innerWidth) popover.style.left = (rect.left - popRect.width - 10) + 'px';
              
              popover.querySelector('.rsj-popover-close').addEventListener('click', () => popover.remove());
              setTimeout(() => {
                  document.addEventListener('click', function cerrar(e) {
                      if (!popover.contains(e.target) && !info.el.contains(e.target)) {
                          popover.remove(); document.removeEventListener('click', cerrar);
                      }
                  });
              }, 100);
          }
      });
      calendar.render();
      
      function generarLeyenda(eventos) {
          const el = document.getElementById('leyenda-deptos');
          const deptos = {};
          eventos.forEach(ev => { const d = ev.extendedProps?.departamento || 'Otro'; if (!deptos[d]) deptos[d] = ev.color || '#95A5A6'; });
          if (!Object.keys(deptos).length) return;
          el.innerHTML = Object.entries(deptos).map(([n, c]) => 
              `<span class="leyenda-item"><span class="leyenda-color" style="background-color:${c}"></span>${n}</span>`
          ).join('');
      }
      
      document.addEventListener('scroll', cerrarPopovers, true);
  });
  </script>
</body>
</html>