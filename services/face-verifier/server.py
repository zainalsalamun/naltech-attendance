"""Loopback-only, authenticated matching PoC. No files/logged photos, no liveness."""
import hmac
from http.server import BaseHTTPRequestHandler, HTTPServer
import json
import os

from verifier import InvalidFaceImage, Verifier

MAX_BODY = 6 * 1024 * 1024


def handler_for(verifier, key):
    class Handler(BaseHTTPRequestHandler):
        def setup(self):
            super().setup()
            self.connection.settimeout(15)

        def log_message(self, *_):
            # Do not print headers, payloads, photos or biometric output.
            pass

        def reply(self, status, data):
            body = json.dumps(data, allow_nan=False).encode()
            self.send_response(status)
            self.send_header("Content-Type", "application/json")
            self.send_header("Cache-Control", "no-store")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(body)

        def authenticated(self):
            return hmac.compare_digest(self.headers.get("Authorization", ""), "Bearer " + key)

        def do_GET(self):
            if not self.authenticated():
                return self.reply(401, {"error": "unauthorized"})
            if self.path != "/health":
                return self.reply(404, {"error": "not_found"})
            self.reply(200, {"engine": "opencv-sface", "liveness": "not_configured",
                             "attendance_eligible": False})

        def do_POST(self):
            if not self.authenticated():
                return self.reply(401, {"error": "unauthorized"})
            if self.path == "/v1/liveness":
                return self.reply(501, {"error": "liveness_not_implemented", "attendance_eligible": False})
            if self.path not in ["/v1/compare", "/v1/liveness-preview"]:
                return self.reply(404, {"error": "not_found"})
            try:
                length = int(self.headers.get("Content-Length", "0"))
                if not 0 < length <= MAX_BODY:
                    return self.reply(413, {"error": "invalid_size"})
                if self.headers.get("Content-Type", "").split(";")[0].strip() != "application/json":
                    return self.reply(415, {"error": "json_required"})
                data = json.loads(self.rfile.read(length))
                fields = {"probe"} if self.path == "/v1/liveness-preview" else {"reference", "probe"}
                if not isinstance(data, dict) or set(data) != fields:
                    return self.reply(422, {"error": "invalid_fields"})
                result = (verifier.liveness_preview(data['probe']) if self.path == '/v1/liveness-preview'
                          else verifier.compare(data["reference"], data["probe"]))
                self.reply(200, result)
            except NotImplementedError:
                self.reply(501, {"error": "experimental_liveness_not_configured", "attendance_eligible": False})
            except (InvalidFaceImage, ValueError, TypeError):
                self.reply(422, {"error": "invalid_photo"})
            except Exception:
                self.reply(503, {"error": "matching_unavailable"})

    return Handler


if __name__ == "__main__":
    secret = os.environ.get("FACE_SERVICE_KEY", "")
    if len(secret) < 32:
        raise SystemExit("Set FACE_SERVICE_KEY to a random secret of at least 32 characters")
    verifier = Verifier()
    server = HTTPServer(("127.0.0.1", int(os.environ.get("FACE_SERVICE_PORT", "8021"))), handler_for(verifier, secret))
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
