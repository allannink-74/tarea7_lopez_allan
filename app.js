document.addEventListener('DOMContentLoaded', () => {
    const filtroTipo = document.getElementById('tipo');
    const filtroPersonas = document.getElementById('personas');
    const estadoBusqueda = document.getElementById('estado-busqueda');
    const listaHabitaciones = document.getElementById('lista-habitaciones');
    const habitacionSelect = document.getElementById('habitacionSelect');
    const formReservacion = document.getElementById('form-reservacion');
    const resultadoReserva = document.getElementById('resultado-reserva');

    buscarHabitaciones();

    filtroTipo.addEventListener('change', buscarHabitaciones);
    filtroPersonas.addEventListener('input', buscarHabitaciones);

    async function buscarHabitaciones() {
        const tipoVal = encodeURIComponent(filtroTipo.value);
        const personasVal = encodeURIComponent(filtroPersonas.value);

        estadoBusqueda.textContent = "Buscando tours disponibles...";
        listaHabitaciones.setAttribute('aria-busy', 'true');
        listaHabitaciones.textContent = "";

        try {
            const res = await fetch(`api/habitaciones.php?tipo=${tipoVal}&personas=${personasVal}`);

            if (!res.ok) {
                const errData = await res.json();
                estadoBusqueda.textContent = errData.error || "Error en la consulta al servidor.";
                listaHabitaciones.setAttribute('aria-busy', 'false');
                actualizarSelectReserva([]);
                return;
            }

            const data = await res.json();
            listaHabitaciones.setAttribute('aria-busy', 'false');

            if (data.total === 0) {
                estadoBusqueda.textContent = "No hay tours con esos criterios";
                actualizarSelectReserva([]);
            } else {
                estadoBusqueda.textContent = `Se encontraron ${data.total} tour(s) disponible(s).`;
                
                data.habitaciones.forEach(hab => {
                    listaHabitaciones.appendChild(crearTarjeta(hab));
                });

                actualizarSelectReserva(data.habitaciones);
            }
        } catch (error) {
            estadoBusqueda.textContent = "Error de conexión con el servidor.";
            listaHabitaciones.setAttribute('aria-busy', 'false');
            actualizarSelectReserva([]);
        }
    }

    function actualizarSelectReserva(habitaciones) {
        habitacionSelect.textContent = "";
        habitaciones.forEach(hab => {
            const opt = document.createElement('option');
            opt.value = hab.id;
            opt.textContent = `${hab.nombre} (${formatearQuetzales(hab.precio)})`;
            habitacionSelect.appendChild(opt);
        });
    }

    formReservacion.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nombreInput = document.getElementById('nombre');
        const correoInput = document.getElementById('correo');
        const nochesInput = document.getElementById('noches');

        if (nombreInput.value.trim() === "") {
            resultadoReserva.className = "msg-error";
            resultadoReserva.textContent = "Por favor escriba su nombre.";
            nombreInput.focus();
            return;
        }

        const datosEnvio = {
            nombre: nombreInput.value.trim(),
            correo: correoInput.value.trim(),
            habitacionId: parseInt(habitacionSelect.value),
            noches: parseInt(nochesInput.value)
        };

        resultadoReserva.className = "";
        resultadoReserva.textContent = "Procesando reservación del tour...";

        try {
            const respuesta = await fetch('api/reservacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datosEnvio)
            });

            const datosRespuesta = await respuesta.json();

            if (respuesta.status === 201) {
                resultadoReserva.className = "msg-exito";
                resultadoReserva.textContent = `¡Reservación confirmada! Código: ${datosRespuesta.codigo} | Tour: ${datosRespuesta.habitacion} | Días: ${datosRespuesta.noches} | Total: ${formatearQuetzales(datosRespuesta.total)}`;
                formReservacion.reset();
            } else if (respuesta.status === 422) {
                resultadoReserva.className = "msg-error";
                resultadoReserva.textContent = "";
                const ul = document.createElement('ul');
                datosRespuesta.errores.forEach(err => {
                    const li = document.createElement('li');
                    li.textContent = err;
                    ul.appendChild(li);
                });
                resultadoReserva.appendChild(ul);
            } else {
                resultadoReserva.className = "msg-error";
                resultadoReserva.textContent = datosRespuesta.error || "Error al procesar la solicitud.";
            }
        } catch (err) {
            resultadoReserva.className = "msg-error";
            resultadoReserva.textContent = "Error de conexión al enviar la reservación.";
        }
    });
});