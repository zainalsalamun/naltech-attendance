"""Experimental MiniFASNet diagnostic, never an attendance authorization.

Preprocessing/scoring follows Minivision's Apache-2.0 Android reference:
Silent-Face-Anti-Spoofing-APK, commit 83329c97ba1153e2bbdd374dee1b5d33a7367dc7.
See notices/Minivision-LICENSE. Model weights are not bundled here.
"""
import json
import math
from pathlib import Path
import struct

from verifier import checked_model


def float32(value):
    """Match the float arithmetic of the pinned C++ CalculateBox reference."""
    return struct.unpack('f', struct.pack('f', value))[0]


def calculate_crop(image_w, image_h, face, requested_scale):
    """YuNet xywh -> reference inclusive box; zero shifts, no org_resize.

    x2 = x + width - 1, so int(x2 - x1 + 1) is int(width).
    Preserve C++ truncation, float32 scaling and right/bottom edge adjustment.
    """
    if image_w < 2 or image_h < 2 or len(face) < 4:
        raise RuntimeError('Invalid face crop')
    if not all(math.isfinite(float(value)) for value in face[:4]):
        raise RuntimeError('Invalid face crop')
    x, y, width, height = (int(value) for value in face[:4])
    if width <= 0 or height <= 0 or not math.isfinite(requested_scale) or requested_scale <= 0:
        raise RuntimeError('Invalid face crop')
    scale = min(float32(requested_scale), float32((image_w - 1) / width),
                float32((image_h - 1) / height))
    crop_w, crop_h = int(float32(width * scale)), int(float32(height * scale))
    center_x, center_y = x + width // 2, y + height // 2
    left, top = center_x - crop_w // 2, center_y - crop_h // 2
    right, bottom = center_x + crop_w // 2, center_y + crop_h // 2
    if left < 0:
        right -= left
        left = 0
    if top < 0:
        bottom -= top
        top = 0
    if right >= image_w:
        shift = right - image_w + 1
        left -= shift
    if bottom >= image_h:
        shift = bottom - image_h + 1
        top -= shift
    if min(left, top) < 0 or min(crop_w, crop_h) <= 0 or left + crop_w > image_w or top + crop_h > image_h:
        raise RuntimeError('Face crop out of bounds')
    return left, top, crop_w, crop_h


def live_probability(values):
    if (len(values) != 3 or any(isinstance(value, bool) or not math.isfinite(float(value))
                              or not 0 <= float(value) <= 1 for value in values)
            or not .99 <= sum(float(value) for value in values) <= 1.01):
        raise RuntimeError('Experimental output invalid')
    return float(values[1])


class ExperimentalLiveness:
    def __init__(self, directory):
        import cv2
        import ncnn
        import numpy as np
        self.cv, self.ncnn, self.np = cv2, ncnn, np
        directory = Path(directory)
        manifest = json.loads((directory / 'manifest.json').read_text())
        expected_files = {'config.json', 'LICENSE', 'model_1.param', 'model_1.bin', 'model_2.param', 'model_2.bin'}
        if set(manifest['files']) != expected_files:
            raise RuntimeError('Incomplete experimental model manifest')
        for filename, item in manifest['files'].items():
            checked_model(directory / filename, item['sha256'])
        config = json.loads((directory / 'config.json').read_text())
        if len(config) != 2 or {row['name'] for row in config} != {'model_1', 'model_2'}:
            raise RuntimeError('Unsupported experimental liveness configuration')
        self.models = []
        for row in config:
            expected_scale = {'model_1': 2.7, 'model_2': 4.0}[row['name']]
            if (row['width'] != 80 or row['height'] != 80 or row['org_resize']
                    or row['shift_x'] != 0 or row['shift_y'] != 0 or row['scale'] != expected_scale):
                raise RuntimeError('Unsupported experimental preprocessing')
            net = ncnn.Net()
            net.opt.use_vulkan_compute = False
            net.opt.num_threads = 1
            if net.load_param(str(directory / (row['name'] + '.param'))) != 0 or net.load_model(str(directory / (row['name'] + '.bin'))) != 0:
                raise RuntimeError('Cannot load experimental liveness model')
            self.models.append((net, float(row['scale'])))

    def diagnose(self, frame, face):
        image_h, image_w = frame.shape[:2]
        scores = []
        for net, requested_scale in self.models:
            left, top, crop_w, crop_h = calculate_crop(image_w, image_h, face, requested_scale)
            crop = frame[top:top + crop_h, left:left + crop_w]
            pixels = self.np.ascontiguousarray(self.cv.resize(crop, (80, 80)))
            tensor = self.ncnn.Mat.from_pixels(pixels, self.ncnn.Mat.PixelType.PIXEL_BGR, 80, 80)
            extractor = net.create_extractor()
            if extractor.input('data', tensor) != 0:
                raise RuntimeError('Experimental input failed')
            result, output = extractor.extract('softmax')
            values = self.np.array(output).reshape(-1)
            if result != 0:
                raise RuntimeError('Experimental output invalid')
            scores.append(live_probability(values.tolist()))
        score = sum(scores) / len(scores)
        return {'liveness': 'experimental', 'candidate_live': score >= .8,
                'diagnostic_score': score, 'attendance_eligible': False}
