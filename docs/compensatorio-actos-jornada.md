# Compensatorio por acto administrativo en una jornada

En Nueva solicitud seleccione Disfrute por acto administrativo. Puede distribuir el tiempo entre uno o varios actos propios, vigentes y aprobados por Nómina, indicando las horas de cada uno. Cero significa que no utiliza ese acto. El formulario muestra la suma seleccionada, el tiempo solicitado y el máximo diario.

Cada solicitud debe corresponder a una única fecha laboral. Puede incluir franjas separadas de esa misma fecha; los descansos se excluyen del cómputo. La suma seleccionada debe coincidir exactamente con los minutos solicitados y no puede superar ocho horas para Trabajador oficial o ocho horas y media para las demás vinculaciones. Los horarios temporales no amplían esos máximos. Si la jornada temporal es menor, únicamente puede utilizarse el tiempo laborable dentro de sus franjas.

El servidor verifica saldo individual, propiedad, aprobación y vigencia de cada acto, y rechaza actos duplicados en una solicitud. Los tiempos se reservan mientras se aprueba, se descuentan con la aprobación final y se liberan conforme a las reglas de rechazo o cancelación. Las aprobaciones individuales y masivas verifican también la única fecha, duración y reservas. Las nuevas solicitudes guardan en su instantánea las franjas de la jornada utilizada para no recalcularlas si después cambia un horario temporal.

Al corregir una solicitud devuelta se recuperan las horas por identificador del acto, no por posición en la lista. El saldo seleccionable incluye la reserva propia que será sustituida al reenviar. No se requiere cambiar el esquema MySQL.
