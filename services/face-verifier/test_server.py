import base64
import hashlib
import http.client
from http.server import HTTPServer
import json
from pathlib import Path
import tempfile
import threading
import unittest

from server import handler_for
from verifier import checked_model, cosine_score, InvalidFaceImage


class FixtureVerifier:
    def liveness_preview(self, probe):
        raise NotImplementedError()

    def compare(self, reference, probe):
        if not reference or not probe:
            raise InvalidFaceImage()
        return {"engine": "opencv-sface", "similarity": .9, "reference_faces": 1,
                "probe_faces": 1, "liveness": "not_configured", "attendance_eligible": False}


class ServerTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = HTTPServer(("127.0.0.1", 0), handler_for(FixtureVerifier(), "fixture-key"))
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()
        cls.thread.join(timeout=2)

    def request(self, path, body, authenticated=True, content_type="application/json", length=None):
        connection = http.client.HTTPConnection("127.0.0.1", self.server.server_port, timeout=3)
        headers = {"Content-Type": content_type}
        if authenticated:
            headers["Authorization"] = "Bearer fixture-key"
        if length is not None:
            headers["Content-Length"] = str(length)
        connection.request("POST", path, body, headers)
        response = connection.getresponse()
        status, data = response.status, json.loads(response.read())
        connection.close()
        return status, data

    def test_auth_and_size_limits(self):
        self.assertEqual(self.request("/v1/compare", "{}", authenticated=False)[0], 401)
        self.assertEqual(self.request("/v1/compare", "", length=7 * 1024 * 1024)[0], 413)
        self.assertEqual(self.request("/v1/compare", "{}", content_type="text/plain")[0], 415)

    def test_bad_payloads_and_faces(self):
        for payload in ["{", "[]", "{}", '{"reference":"","probe":""}', '{"reference":"a","probe":"b","verified":true}']:
            self.assertEqual(self.request("/v1/compare", payload)[0], 422)

    def test_matching_never_claims_liveness_or_attendance(self):
        status, data = self.request("/v1/compare", json.dumps({"reference": "fixture", "probe": "fixture"}))
        self.assertEqual(status, 200)
        self.assertFalse(data["attendance_eligible"])
        self.assertEqual(data["liveness"], "not_configured")
        status, data = self.request('/v1/liveness-preview', '{"probe":"fixture"}')
        self.assertEqual(status, 501)
        self.assertFalse(data['attendance_eligible'])
        status, data = self.request("/v1/liveness", "{}")
        self.assertEqual(status, 501)
        self.assertFalse(data["attendance_eligible"])

    def test_model_checksum_is_mandatory(self):
        with tempfile.TemporaryDirectory() as folder:
            model = Path(folder) / "fixture.onnx"
            model.write_bytes(b"fixture-only-not-a-model")
            with self.assertRaises(RuntimeError):
                checked_model(model, "0" * 64)
            self.assertEqual(checked_model(model, hashlib.sha256(model.read_bytes()).hexdigest()), str(model))

    def test_cosine_roundoff_is_bounded_but_invalid_scores_are_rejected(self):
        self.assertEqual(cosine_score(1.00000005), 1)
        self.assertEqual(cosine_score(-1.00000005), -1)
        for value in [float('nan'), float('inf'), 1.1]:
            with self.assertRaises(RuntimeError):
                cosine_score(value)


if __name__ == "__main__":
    unittest.main()
