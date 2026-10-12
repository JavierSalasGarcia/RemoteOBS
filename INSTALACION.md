# Instalación paso a paso

Sitio web: https://coniitec.org/auobs/ — Carpeta en cada computadora: `C:\auobs\`

## Parte 1. Sitio web (Hostinger)

1. En hPanel entra a Bases de datos, MySQL, y crea una base de datos con su usuario y contraseña. Anota los tres datos: nombre de la base, usuario y contraseña. El servidor casi siempre es `localhost`.

2. En tu copia local de la carpeta `web`, abre `config.php` con el Bloc de notas y cambia estos valores: `DB_NAME`, `DB_USER` y `DB_PASS` por los de la base de datos; `PANEL_PASSWORD` por la contraseña con la que entrarás al panel; y `AGENT_TOKEN` por una cadena larga y aleatoria (por ejemplo, 30 letras y números mezclados). Guarda el token en un lugar seguro, porque lo necesitarás en las dos computadoras.

3. En hPanel abre el Administrador de archivos (o conéctate por FTP), entra a `public_html` y crea la carpeta `auobs`.

4. Sube a `public_html/auobs/` estos cuatro archivos: `config.php`, `db.php`, `api.php` e `index.php`. Deben quedar directamente dentro de `auobs`, no dentro de otra subcarpeta `web`.

5. Abre https://coniitec.org/auobs/ en el navegador. Debe aparecer una pantalla que pide la clave. Escribe la de `PANEL_PASSWORD`. Si ves el panel con las dos computadoras (sin conexión por ahora) y tres horarios, el sitio quedó listo. Si aparece un error de base de datos, revisa los tres datos del paso 2.

6. En el panel revisa que los horarios sean: Miércoles 14/10/2026 de 10:45 a 17:30, Jueves 15/10/2026 de 09:45 a 18:00 y Viernes 16/10/2026 de 08:45 a 10:30. Todos en hora CDMX, con 1 minuto de antelación para abrir la transmisión. Si algo difiere, corrígelo y pulsa Guardar en esa tarjeta.

## Parte 2. Cada computadora (repite en la PC 1 y en la PC 2)

7. Crea la carpeta `C:\auobs\` y copia dentro todo el contenido de la carpeta `agent`: `agent.py`, `requirements.txt`, `iniciar_agente.bat` y `config.example.json`.

8. Abre el Símbolo del sistema (tecla Windows, escribe `cmd`, Enter) y ejecuta estos dos comandos:

   ```
   cd /d C:\auobs
   pip install -r requirements.txt
   ```

   Si `pip` no se reconoce, usa `python -m pip install -r requirements.txt`.

9. En `C:\auobs\` copia `config.example.json` y renombra la copia a `config.json`. Ábrelo con el Bloc de notas y déjalo así, cambiando solo lo indicado:

   ```
   {
     "agent_id": "pc1",
     "server_url": "https://coniitec.org/auobs/api.php",
     "token": "EL MISMO AGENT_TOKEN DE config.php",
     "obs_host": "localhost",
     "obs_port": 4455,
     "obs_password": "LA CLAVE QUE PONDRÁS EN OBS (paso 10)",
     "obs_exe": "C:\\Program Files\\obs-studio\\bin\\64bit\\obs64.exe"
   }
   ```

   En la PC 1 usa `"agent_id": "pc1"` y en la PC 2 usa `"agent_id": "pc2"`. Las barras de `obs_exe` van dobles, como se ve arriba.

## Parte 3. OBS (en cada computadora)

10. En OBS abre Herramientas, Ajustes del servidor WebSocket. Marca "Habilitar servidor WebSocket", deja el puerto 4455, marca "Habilitar autenticación" y define una contraseña. Esa es la que va en `obs_password` del paso 9.

11. Verifica que existan las escenas `miercoles`, `jueves` y `viernes`, cada una con una fuente multimedia llamada igual que la escena (`miercoles`, `jueves`, `viernes`) que apunte al archivo `.mp4` correspondiente. Si prefieres otros nombres, puedes cambiarlos después en el panel.

12. Abre las propiedades de cada fuente multimedia y desmarca "Repetir" y "Reiniciar la reproducción cuando la fuente se active". Deja desmarcado también "Cerrar archivo cuando no esté activo".

13. En Ajustes, Emisión, elige Servicio "Personalizado". En la PC 1 pon el servidor `rtmp://a.rtmp.youtube.com/live2`. En la PC 2 pon `rtmp://b.rtmp.youtube.com/live2?backup=1`. En ambas pon la misma clave de transmisión de YouTube.

14. En Ajustes, Salida, usa la misma resolución y velocidad de bits en las dos computadoras (por ejemplo 1080p y 4500 kbps de video), para que la transmisión de respaldo sea idéntica.

## Parte 4. YouTube

15. Haz esto hoy mismo, porque puede tardar. Entra a YouTube con la cuenta del canal, abre youtube.com/verify y verifica el canal por teléfono. Después entra a YouTube Studio, pulsa Crear, Transmitir en vivo, y si es la primera vez, activa la función de transmisión en vivo. YouTube puede tardar hasta 24 horas en habilitarla.

16. Crea la clave reutilizable. En Studio pulsa Crear, Transmitir en vivo, y elige la pestaña Transmisión. En el apartado de clave de transmisión selecciona "Crear nueva clave de transmisión", ponle un nombre como "Congreso" y marca que sea reutilizable. Con esa clave se transmitirán la prueba y los tres días. Copia la clave y las dos direcciones que YouTube muestra: el servidor principal y el servidor de respaldo. Son las que se escriben en OBS en el paso 13.

17. Crea la transmisión de prueba. En la misma pantalla pulsa Programar transmisión. Ponle el título "PRUEBA", elige visibilidad "Privada" y escoge la fecha y la hora de la prueba (la misma que dejarás en el panel). En la configuración selecciona la clave "Congreso", activa el inicio automático y la finalización automática, y activa el DVR para poder retroceder. Indica que no es contenido para niños y guarda.

18. Durante la prueba, entra a la Sala de control en vivo de esa transmisión (Studio, Contenido, En vivo, abre la prueba). Debes ver la señal recibida, el estado de la transmisión en verde y la indicación de que llega por el servidor principal y por el de respaldo. Abre el enlace privado en otro navegador con tu cuenta para confirmar imagen y audio.

19. Al terminar la prueba, comprueba que YouTube cerró la transmisión por sí sola cuando el agente la detuvo. Elimina o deja privado el video de la prueba y conserva la clave "Congreso".

20. Crea las tres transmisiones definitivas, una por día, con la misma clave "Congreso". Pulsa Programar transmisión para cada una y configúralas así: Miércoles con fecha del 14 de octubre y hora 10:44, Jueves del 15 de octubre a las 9:44, y Viernes del 16 de octubre a las 8:44. Ponles un título claro, descripción con el programa del día y sus horas, miniatura, visibilidad Pública (o No listada si solo compartirás el enlace), inicio y finalización automáticos, y DVR activado. Se programan a un minuto antes del video porque el agente abre la transmisión 1 minuto antes del inicio.

21. Cuida que las tres transmisiones usen exactamente la misma clave y que no se traslapen en el horario. Desde Studio, Contenido, En vivo, Programadas, copia el enlace de cada día para compartirlo. Deja las tres programadas desde ahora para no hacerlo con prisa.

## Parte 5. Primera prueba

22. Con OBS abierto, ejecuta en la carpeta `C:\auobs` el comando `python agent.py` desde el Símbolo del sistema. Debes ver mensajes como "Agente pc1 iniciado" y "Conectado a OBS".

23. Vuelve al panel (https://coniitec.org/auobs/). En unos segundos la computadora debe aparecer "Agente en línea" con OBS conectado. Si no, revisa en `agent.log` (dentro de `C:\auobs\`) el motivo; los casos más comunes son un token distinto al de `config.php` o la contraseña de OBS mal escrita.

24. Detén el agente con Ctrl+C. Para el ensayo, edita en el panel el horario del miércoles: inicio a unos 15 minutos de ahora y fin 10 minutos después, con una transmisión de YouTube privada. Vuelve a ejecutar el agente en las dos computadoras y comprueba la transmisión, el video en el mismo segundo y el relevo cerrando OBS en la PC 1. Al terminar, restablece los horarios reales.

## Parte 6. Que arranque solo

25. En cada computadora presiona Windows + R, escribe `shell:startup` y Enter. Dentro de esa carpeta crea un acceso directo a `C:\auobs\iniciar_agente.bat`. Así el agente se vuelve a abrir si la computadora se reinicia.

26. En Configuración de Windows, Sistema, Inicio/apagado y suspensión, pon "Nunca" en suspensión y apagado de pantalla mientras esté conectada. Pausa también las actualizaciones de Windows durante la semana del congreso y desactiva el protector de pantalla.

27. En Windows, Cuentas, opciones de inicio de sesión, activa que la computadora inicie sesión automáticamente (o confirma que lo haga) para que tras un reinicio se abra el acceso directo del paso 25. Abre OBS una vez al día antes de salir para confirmar que todo está en verde en el panel.

## Antes de salir el miércoles

Entra al panel desde el teléfono y comprueba que ambas computadoras aparezcan en línea, con OBS conectado, y que la fase diga "preparando" o "sin evento activo" según la hora. Verifica en YouTube Studio que el evento programado esté listo con la clave correcta.
