"""Agente de RemoteOBS: controla OBS local y se coordina con el panel web."""
import json
import logging
import logging.handlers
import os
import subprocess
import sys
import time

import obsws_python as obs
import requests

BASE = os.path.dirname(os.path.abspath(__file__))
CFG = json.load(open(os.path.join(BASE, "config.json"), encoding="utf-8"))
CACHE_FILE = os.path.join(BASE, "cache.json")

SYNC_EVERY = 5          # segundos entre consultas al panel
TOLERANCE = 3.0         # desfase máximo (s) del video antes de corregir
SEEK_COOLDOWN = 10      # segundos mínimos entre correcciones de posición
START_COOLDOWN = 10     # segundos mínimos entre intentos de iniciar transmisión
LAUNCH_COOLDOWN = 90    # segundos mínimos entre intentos de abrir OBS
STOP_WINDOW = 600       # tras el fin del evento, se detiene la transmisión durante esta ventana

PLAY = "OBS_WEBSOCKET_MEDIA_INPUT_ACTION_PLAY"
PAUSE = "OBS_WEBSOCKET_MEDIA_INPUT_ACTION_PAUSE"

log = logging.getLogger("agent")
log.setLevel(logging.INFO)
_h = logging.handlers.RotatingFileHandler(os.path.join(BASE, "agent.log"), maxBytes=1_000_000, backupCount=3, encoding="utf-8")
_h.setFormatter(logging.Formatter("%(asctime)s %(message)s"))
log.addHandler(_h)
log.addHandler(logging.StreamHandler(sys.stdout))

outbox = []  # mensajes pendientes de enviar al panel


def say(msg):
    log.info(msg)
    outbox.append(msg)


class Agent:
    def __init__(self):
        self.cl = None
        self.events = []
        self.auto = 1
        self.offset = 0.0  # reloj del servidor menos reloj local
        self.commands = []
        self.stopped_events = set()
        self.last = {"sync": 0, "start": 0, "seek": 0, "launch": 0}
        self.status = {"obs_connected": False, "streaming": False, "phase": "iniciando", "msg": ""}
        self.server_ok = None
        self._load_cache()

    # ---------- reloj y caché ----------
    def now(self):
        return time.time() + self.offset

    def _load_cache(self):
        try:
            c = json.load(open(CACHE_FILE, encoding="utf-8"))
            self.events, self.auto, self.offset = c["events"], c["auto"], c["offset"]
        except Exception:
            pass

    def _save_cache(self):
        try:
            json.dump({"events": self.events, "auto": self.auto, "offset": self.offset}, open(CACHE_FILE, "w", encoding="utf-8"))
        except Exception:
            pass

    # ---------- panel ----------
    def sync(self):
        self.last["sync"] = time.time()
        payload = {"agent_id": CFG["agent_id"], "status": self.status, "log": outbox[:40]}
        try:
            t0 = time.time()
            r = requests.post(CFG["server_url"] + "?action=agent_sync", json=payload,
                              headers={"X-Token": CFG["token"]}, timeout=6)
            r.raise_for_status()
            d = r.json()
            t1 = time.time()
            del outbox[:len(payload["log"])]
            self.offset = d["server_time"] - (t0 + t1) / 2
            self.events = [{k: (int(v) if k not in ("name", "scene", "input_name") else v) for k, v in e.items()} for e in d["events"]]
            self.auto = int(d["auto"])
            self.commands += [c["cmd"] for c in d["commands"]]
            self._save_cache()
            if self.server_ok is False:
                say("Conexión con el panel restablecida")
            self.server_ok = True
        except Exception as ex:
            if self.server_ok is not False:
                log.info("Sin conexión con el panel (se sigue con el horario guardado): %s", ex)
            self.server_ok = False

    # ---------- OBS ----------
    def connect(self):
        if self.cl:
            return True
        try:
            self.cl = obs.ReqClient(host=CFG.get("obs_host", "localhost"), port=CFG.get("obs_port", 4455),
                                    password=CFG.get("obs_password", ""), timeout=4)
            say("Conectado a OBS")
            return True
        except Exception:
            self.cl = None
            exe = CFG.get("obs_exe")
            if exe and os.path.exists(exe) and time.time() - self.last["launch"] > LAUNCH_COOLDOWN:
                self.last["launch"] = time.time()
                say("OBS no responde; intentando abrirlo")
                try:
                    subprocess.Popen([exe, "--disable-shutdown-check"], cwd=os.path.dirname(exe))
                except Exception as ex:
                    log.info("No se pudo abrir OBS: %s", ex)
            return False

    def is_streaming(self):
        return bool(self.cl.get_stream_status().output_active)

    def start_stream(self, why):
        if time.time() - self.last["start"] < START_COOLDOWN:
            return
        self.last["start"] = time.time()
        say(f"Iniciando transmisión ({why})")
        try:
            self.cl.start_stream()
        except Exception as ex:
            log.info("start_stream falló: %s", ex)

    def stop_stream(self, why):
        say(f"Deteniendo transmisión ({why})")
        try:
            self.cl.stop_stream()
        except Exception as ex:
            log.info("stop_stream falló: %s", ex)

    def current_scene(self):
        r = self.cl.get_current_program_scene()
        return getattr(r, "current_program_scene_name", None) or getattr(r, "scene_name", None)

    # ---------- lógica principal ----------
    def pick_event(self, t):
        for e in self.events:
            if e["start_at"] - e["prestart_sec"] <= t < e["end_at"]:
                return e
        return None

    def media_step(self, ev, t):
        inp = ev["input_name"] or ev["scene"]
        st = self.cl.get_media_input_status(inp)
        state = st.media_state
        cursor = (st.media_cursor or 0) / 1000.0
        dur = (st.media_duration or 0) / 1000.0
        expected = t - ev["start_at"]
        self.status.update(media_pos=round(cursor, 1), expected_pos=round(expected, 1))
        can_seek = time.time() - self.last["seek"] > SEEK_COOLDOWN

        if expected < 0:  # espera: primer fotograma en pausa
            if state in ("OBS_MEDIA_STATE_STOPPED", "OBS_MEDIA_STATE_ENDED", "OBS_MEDIA_STATE_NONE"):
                self.cl.trigger_media_input_action(inp, PLAY)
            elif state == "OBS_MEDIA_STATE_PLAYING" or cursor > 0.5:
                self.cl.set_media_input_cursor(inp, 0)
                self.cl.trigger_media_input_action(inp, PAUSE)
            return

        if dur and expected >= dur:
            return  # el video ya terminó; se espera la hora de fin
        if state == "OBS_MEDIA_STATE_PLAYING":
            if abs(cursor - expected) > TOLERANCE and can_seek:
                self.last["seek"] = time.time()
                say(f"Corrigiendo posición del video: {cursor:.1f}s -> {expected:.1f}s")
                self.cl.set_media_input_cursor(inp, int(expected * 1000))
        elif state == "OBS_MEDIA_STATE_PAUSED":
            self.last["seek"] = time.time()
            self.cl.set_media_input_cursor(inp, int(expected * 1000))
            self.cl.trigger_media_input_action(inp, PLAY)
            say(f"Reanudando video en {expected:.1f}s")
        else:
            self.cl.trigger_media_input_action(inp, PLAY)  # se corrige en la siguiente vuelta

    def tick(self):
        t = self.now()
        if not self.connect():
            self.status.update(obs_connected=False, streaming=False, msg="OBS no disponible")
            return
        cmds, self.commands = self.commands, []
        try:
            streaming = self.is_streaming()
            self.status.update(obs_connected=True, streaming=streaming, msg="")
            ev = self.pick_event(t)
            self.status["event"] = ev["name"] if ev else None

            for c in cmds:
                say(f"Orden recibida: {c}")
                if c == "start" and not streaming:
                    self.start_stream("orden manual")
                elif c == "stop" and streaming:
                    self.stop_stream("orden manual")
                elif c == "resync":
                    self.last["seek"] = 0
                    if ev:
                        self.media_step(ev, t)

            if not self.auto:
                self.status["phase"] = "automático apagado"
                return

            if ev:
                started = t >= ev["start_at"]
                self.status["phase"] = "en vivo" if started else "preparando (video en pausa)"
                if self.current_scene() != ev["scene"]:
                    self.cl.set_current_program_scene(ev["scene"])
                    say(f"Escena cambiada a {ev['scene']}")
                if not streaming:
                    self.start_stream("horario automático")
                self.media_step(ev, t)
                return

            self.status["phase"] = "sin evento activo"
            self.status.pop("expected_pos", None)
            for e in self.events:
                if 0 <= t - e["end_at"] < STOP_WINDOW and e["id"] not in self.stopped_events:
                    self.stopped_events.add(e["id"])
                    if streaming:
                        self.stop_stream(f"fin de {e['name']}")
                    self.status["phase"] = f"{e['name']} finalizado"
        except Exception as ex:
            log.info("Error hablando con OBS: %s", ex)
            self.cl = None
            self.status.update(obs_connected=False, msg=f"Error OBS: {ex}"[:150])


def main():
    a = Agent()
    say(f"Agente {CFG['agent_id']} iniciado")
    while True:
        try:
            if time.time() - a.last["sync"] >= SYNC_EVERY:
                a.sync()
            a.tick()
        except Exception as ex:  # nunca debe caerse el bucle
            log.info("Error inesperado: %s", ex)
        time.sleep(1)


if __name__ == "__main__":
    main()
