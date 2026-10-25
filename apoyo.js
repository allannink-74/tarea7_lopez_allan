function formatearQuetzales(monto) {
    return new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' }).format(monto);
}

function crearTarjeta(habitacion) {
    const li = document.createElement('li');
    li.className = 'tarjeta';

    const img = document.createElement('img');
    img.src = habitacion.imagen || 'https://via.placeholder.com/600x400?text=Tour+Antigua';
    img.alt = habitacion.nombre;
    img.className = 'tarjeta-img';

    const contenido = document.createElement('div');
    contenido.className = 'tarjeta-contenido';

    const titulo = document.createElement('h3');
    titulo.textContent = habitacion.nombre;

    const detalle = document.createElement('p');
    detalle.textContent = `Categoría: ${habitacion.tipo} · Hasta ${habitacion.capacidad} persona(s)`;

    const vista = document.createElement('p');
    vista.textContent = habitacion.vistaLago ? 'Incluye transporte y guía' : 'Recorrido a pie';

    const precio = document.createElement('p');
    precio.className = 'precio';
    precio.textContent = `${formatearQuetzales(habitacion.precio)} por persona/día`;

    contenido.append(titulo, detalle, vista, precio);
    li.append(img, contenido);
    return li;
}