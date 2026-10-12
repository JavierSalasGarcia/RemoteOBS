# RemoteOBS

Panel web en PHP (carpeta `web`) y un agente en Python por computadora (carpeta `agent`) que controla OBS por obs-websocket. Ambas computadoras transmiten a la misma clave de YouTube, una al servidor principal y otra al de respaldo, y cada una posiciona el video según el reloj, por lo que el relevo es transparente.

## Panel (Hostinger)

Sube el contenido de `web` a una carpeta del sitio, por ejemplo `/obs`. Crea una base de datos MySQL en hPanel y escribe sus datos en `config.php`, junto con la clave del panel y un token largo para los agentes. Al abrir el panel por primera vez se crean las tablas y los tres horarios (14, 15 y 16 de octubre de 2026, hora CDMX), que se pueden editar desde la página.

## Agente (cada computadora)

Instala las dependencias con `pip install -r requirements.txt`. Copia `config.example.json` como `config.json`, pon `agent_id` en `pc1` o `pc2`, la URL de `api.php`, el token y la clave de obs-websocket. Ejecuta `iniciar_agente.bat` (colócale un acceso directo en `shell:startup` para que arranque con Windows).

## OBS (cada computadora)

Activa Herramientas, Ajustes del servidor WebSocket, con contraseña. En Ajustes, Emisión, usa servicio Personalizado con la misma clave de YouTube: la PC 1 con el servidor `rtmp://a.rtmp.youtube.com/live2` y la PC 2 con `rtmp://b.rtmp.youtube.com/live2?backup=1`. La fuente multimedia de cada escena debe tener desmarcados "Repetir" y "Reiniciar la reproducción cuando la fuente se active", y su nombre debe coincidir con el configurado en el panel (por defecto, igual al de la escena).
