"""Original ONNX MN3 experiment. Does not issue attendance authorization.

Input/output contract: pinned Open Model Zoo anti-spoof-mn3 model.yml.
Crop expansion follows kprokofi/light-weight-face-anti-spoofing demo FaceDetector
(1.1 horizontal, 1.05 vertical). YuNet replaces that demo's detector.
See notices/MN3-upstream-LICENSE for upstream attribution.
"""
import hashlib
import json
import math
from pathlib import Path

from download_mn3 import MODEL_NAME, MODEL_SHA384, MODEL_SIZE, OMZ_COMMIT
from verifier import checked_model, InvalidFaceImage

MEAN = (151.2405, 119.5950, 107.8395)
STD = (63.0105, 56.4570, 55.0035)


def mn3_crop(image_w, image_h, face):
    if image_w < 1 or image_h < 1 or len(face) < 4 or not all(math.isfinite(float(v)) for v in face[:4]):
        raise InvalidFaceImage('Invalid face crop')
    x, y, width, height = (float(v) for v in face[:4])
    if width <= 0 or height <= 0:
        raise InvalidFaceImage('Invalid face crop')
    left, top, right, bottom = int(x), int(y), int(x + width), int(y + height)
    dx, dy = (right - left) * (1.1 - 1.) / 2, (bottom - top) * (1.05 - 1.) / 2
    left, right = max(0, int(left - dx)), min(image_w, int(right + dx))
    top, bottom = max(0, int(top - dy)), min(image_h, int(bottom + dy))
    if right <= left or bottom <= top:
        raise InvalidFaceImage('Face crop outside image')
    return left, top, right, bottom


def mn3_live_probability(values):
    if (len(values) != 2 or any(isinstance(v, bool) or not math.isfinite(float(v))
                               or not 0 <= float(v) <= 1 for v in values)
            or not .99 <= sum(float(v) for v in values) <= 1.01):
        raise RuntimeError('MN3 output invalid')
    return float(values[0])  # MN3 live=0; MiniFASNet live=1. Do not share adapters.


def prepare_input(frame, face):
    import cv2
    import numpy as np
    left, top, right, bottom = mn3_crop(frame.shape[1], frame.shape[0], face)
    pixels = cv2.resize(frame[top:bottom, left:right], (128, 128), interpolation=cv2.INTER_CUBIC)
    rgb = cv2.cvtColor(pixels, cv2.COLOR_BGR2RGB).astype(np.float32)
    normalized = (rgb - np.asarray(MEAN, dtype=np.float32)) / np.asarray(STD, dtype=np.float32)
    return np.ascontiguousarray(normalized.transpose(2, 0, 1)[None])


class ExperimentalMn3:
    def __init__(self, directory):
        import cv2
        directory = Path(directory)
        manifest = json.loads((directory / 'manifest.json').read_text())
        if manifest['engine'] != 'anti-spoof-mn3-original-onnx' or manifest['omz_commit'] != OMZ_COMMIT:
            raise RuntimeError('Unsupported MN3 manifest')
        expected = {MODEL_NAME, 'model.yml', 'accuracy-check.yml', 'upstream-LICENSE'}
        if set(manifest['files']) != expected:
            raise RuntimeError('Incomplete MN3 manifest')
        for name, item in manifest['files'].items():
            checked_model(directory / name, item['sha256'])
        raw = (directory / MODEL_NAME).read_bytes()
        if len(raw) != MODEL_SIZE or hashlib.sha384(raw).hexdigest() != MODEL_SHA384:
            raise RuntimeError('Unrecognized MN3 model')
        self.net = cv2.dnn.readNetFromONNX(str(directory / MODEL_NAME))
        self.net.setPreferableBackend(cv2.dnn.DNN_BACKEND_OPENCV)
        self.net.setPreferableTarget(cv2.dnn.DNN_TARGET_CPU)

    def diagnose(self, frame, face):
        self.net.setInput(prepare_input(frame, face), 'actual_input_1')
        values = self.net.forward('output1').reshape(-1).tolist()
        score = mn3_live_probability(values)
        return {'engine': 'anti-spoof-mn3-original-onnx', 'liveness': 'experimental',
                'diagnostic_score': score, 'candidate_live': score >= .8,
                'attendance_eligible': False}
