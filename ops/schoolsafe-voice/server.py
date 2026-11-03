"""Voix de JASPE (03/11) : synthese vocale francaise locale (Piper), reseau interne seulement.

Contrat attendu par jaspe-voice.php :
  GET  /health      -> {"ready": true}
  POST /synthesize  -> audio/wav ; corps {"text": "..."} ; en-tete Authorization: Bearer <jeton 64 hex>
Aucun texte n'est conserve ni journalise ; aucun acces Internet n'est necessaire apres l'installation.
"""
import hmac
import json
import os
import subprocess
import tempfile
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PIPER = os.environ.get('PIPER_BIN', '/opt/piper/piper')
MODEL = os.environ.get('PIPER_MODEL', '/opt/voices/fr_FR-siwis-medium.onnx')
TOKEN_FILE = os.environ.get('VOICE_TOKEN_FILE', '/run/voice-token')
MAX_TEXT = 400
SLOTS = threading.BoundedSemaphore(2)


def token():
    try:
        with open(TOKEN_FILE, encoding='ascii') as handle:
            value = handle.read().strip()
        return value if len(value) == 64 else ''
    except OSError:
        return ''


def ready():
    return os.path.isfile(PIPER) and os.path.isfile(MODEL) and os.path.isfile(MODEL + '.json') and token() != ''


class Handler(BaseHTTPRequestHandler):
    server_version = 'SchoolSafeVoice/1'
    sys_version = ''

    def log_message(self, *args):  # aucun texte ni adresse dans les journaux
        return

    def reply(self, status, body, content_type='application/json'):
        self.send_response(status)
        self.send_header('Content-Type', content_type)
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.end_headers()
        self.wfile.write(body)

    def fail(self, status, code):
        self.reply(status, json.dumps({'error': code}).encode())

    def do_GET(self):
        if self.path != '/health':
            return self.fail(404, 'NOT_FOUND')
        return self.reply(200, json.dumps({'ready': ready()}).encode())

    def do_POST(self):
        if self.path != '/synthesize':
            return self.fail(404, 'NOT_FOUND')
        expected = token()
        given = self.headers.get('Authorization', '')
        if not expected or not hmac.compare_digest(given, 'Bearer ' + expected):
            return self.fail(401, 'UNAUTHORIZED')
        try:
            length = int(self.headers.get('Content-Length', '0'))
        except ValueError:
            return self.fail(400, 'INVALID_REQUEST')
        if length <= 0 or length > 4096:
            return self.fail(413, 'TOO_LARGE')
        try:
            data = json.loads(self.rfile.read(length).decode('utf-8'))
        except (ValueError, UnicodeDecodeError):
            return self.fail(400, 'INVALID_REQUEST')
        text = data.get('text') if isinstance(data, dict) else None
        if not isinstance(text, str) or not text.strip() or len(text) > MAX_TEXT:
            return self.fail(400, 'INVALID_TEXT')
        text = ' '.join(text.split())
        if not SLOTS.acquire(timeout=20):
            return self.fail(429, 'BUSY')
        audio, code = b'', 1
        handle, path = tempfile.mkstemp(suffix='.wav', dir='/tmp')
        os.close(handle)
        try:
            run = subprocess.run([PIPER, '--model', MODEL, '--output_file', path],
                                 input=text.encode('utf-8'), capture_output=True, timeout=60, check=False)
            code = run.returncode
            with open(path, 'rb') as wav:
                audio = wav.read()
        except subprocess.TimeoutExpired:
            return self.fail(503, 'TIMEOUT')
        finally:
            SLOTS.release()
            try:
                os.unlink(path)
            except OSError:
                pass
        if code != 0 or audio[:4] != b'RIFF' or audio[8:12] != b'WAVE':
            return self.fail(503, 'SYNTHESIS_FAILED')
        return self.reply(200, audio, 'audio/wav')


if __name__ == '__main__':
    ThreadingHTTPServer(('0.0.0.0', 18893), Handler).serve_forever()
