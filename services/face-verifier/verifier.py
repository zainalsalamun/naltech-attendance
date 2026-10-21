"""Local PoC for 1:1 face matching. Does not implement liveness or attendance."""
import base64
from io import BytesIO
import os
from pathlib import Path
import hashlib
import math

MAX_IMAGE_BYTES = 2 * 1024 * 1024


class InvalidFaceImage(ValueError):
    pass


def cosine_score(value):
    score = float(value)
    if not math.isfinite(score) or abs(score) > 1.00001:
        raise RuntimeError("Invalid cosine score")
    # Float32 dot products can exceed 1 by a few ulps for identical images.
    return max(-1.0, min(1.0, score))


def checked_model(path, expected_hash):
    path = Path(path)
    if len(expected_hash) != 64 or not path.is_file():
        raise RuntimeError("Configure a model file and its pinned SHA256")
    if hashlib.sha256(path.read_bytes()).hexdigest() != expected_hash.lower():
        raise RuntimeError("Model checksum mismatch")
    return str(path)


class Verifier:
    def __init__(self):
        import cv2
        import numpy as np
        from PIL import Image, ImageOps

        self.cv, self.np, self.Image, self.ImageOps = cv2, np, Image, ImageOps
        Image.MAX_IMAGE_PIXELS = 4096 * 4096
        cv2.setNumThreads(1)
        self.detector = cv2.FaceDetectorYN.create(
            checked_model(os.environ["FACE_YUNET_MODEL"], os.environ["FACE_YUNET_SHA256"]),
            "", (640, 640), score_threshold=0.9,
        )
        self.recognizer = cv2.FaceRecognizerSF.create(
            checked_model(os.environ["FACE_SFACE_MODEL"], os.environ["FACE_SFACE_SHA256"]), "", 0, 0,
        )
        self.experimental_liveness = None
        if os.environ.get('FACE_LIVENESS_EXPERIMENTAL_ENABLED') == 'true':
            engine = os.environ.get('FACE_LIVENESS_EXPERIMENTAL_ENGINE', 'minifasnet')
            if engine == 'minifasnet':
                from experimental_liveness import ExperimentalLiveness
                self.experimental_liveness = ExperimentalLiveness(os.environ['FACE_LIVENESS_MODEL_DIR'])
            elif engine == 'mn3':
                from experimental_mn3 import ExperimentalMn3
                self.experimental_liveness = ExperimentalMn3(os.environ['FACE_MN3_MODEL_DIR'])
            else:
                raise RuntimeError('Unknown experimental liveness engine')

    def _face(self, encoded):
        if not isinstance(encoded, str) or len(encoded) > 4 * ((MAX_IMAGE_BYTES + 2) // 3):
            raise InvalidFaceImage("Invalid photo size")
        try:
            raw = base64.b64decode(encoded, validate=True)
            if not raw or len(raw) > MAX_IMAGE_BYTES:
                raise InvalidFaceImage("Invalid photo size")
            with self.Image.open(BytesIO(raw)) as image:
                if image.format not in ["JPEG", "PNG"]:
                    raise InvalidFaceImage("JPEG/PNG required")
                if not all(320 <= side <= 4096 for side in image.size):
                    raise InvalidFaceImage("Invalid photo dimensions")
                image = self.ImageOps.exif_transpose(image).convert("RGB")
                frame = self.cv.cvtColor(self.np.asarray(image), self.cv.COLOR_RGB2BGR)
        except InvalidFaceImage:
            raise
        except Exception:
            raise InvalidFaceImage("Cannot decode photo") from None
        # Bound detector work while preserving original-image landmark geometry.
        height, width = frame.shape[:2]
        scale = min(1.0, 960 / max(height, width))
        if scale < 1:
            frame = self.cv.resize(frame, (round(width * scale), round(height * scale)))
        self.detector.setInputSize((frame.shape[1], frame.shape[0]))
        _, faces = self.detector.detect(frame)
        if faces is None or len(faces) != 1:
            raise InvalidFaceImage("Exactly one face required")
        if min(faces[0][2:4]) < 80:
            raise InvalidFaceImage("Face too small")
        return frame, faces[0]

    def _feature(self, encoded):
        frame, face = self._face(encoded)
        aligned = self.recognizer.alignCrop(frame, face)
        return self.recognizer.feature(aligned)

    def liveness_preview(self, probe):
        if self.experimental_liveness is None:
            raise NotImplementedError()
        return self.experimental_liveness.diagnose(*self._face(probe))

    def compare(self, reference, probe):
        first, second = self._feature(reference), self._feature(probe)
        similarity = cosine_score(self.recognizer.match(first, second, self.cv.FaceRecognizerSF_FR_COSINE))
        return {"engine": "opencv-sface", "similarity": similarity,
                "reference_faces": 1, "probe_faces": 1,
                "liveness": "not_configured", "attendance_eligible": False}
