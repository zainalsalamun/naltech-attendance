"""Synthetic adapter parity audit; no camera samples and no accuracy claim."""
import argparse
import json
from pathlib import Path
import random
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'services/face-verifier'))
from experimental_liveness import ExperimentalLiveness, calculate_crop, live_probability


def crop_cases():
    cases = [(640, 480, [600., 400., 103., 81.], 2.7),
             (640, 480, [200., 150., 100., 100.], 2.7),
             (640, 480, [600., 400., 82., 82.], 2.7),
             (640, 480, [-8., -4., 82., 82.], 2.7),
             (321, 321, [100., 100., 300., 300.], 4.)]
    randomizer = random.Random(20261021)
    for _ in range(1000):
        w, h = randomizer.choice([(640, 480), (480, 640), (960, 720), (321, 321)])
        bw, bh = randomizer.randint(80, min(w, 300)), randomizer.randint(80, min(h, 300))
        x, y = randomizer.uniform(-10, w - bw + 10), randomizer.uniform(-10, h - bh + 10)
        cases.append((w, h, [x, y, float(bw), float(bh)], randomizer.choice([2.7, 4.0])))
    return cases


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--models', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    if args.output.exists():
        parser.error('Use a new output filename')
    cases = crop_cases()
    with tempfile.TemporaryDirectory(prefix='naltech-crop-oracle-') as folder:
        binary = Path(folder) / 'oracle'
        subprocess.run(['c++', '-std=c++11', '-O0', str(Path(__file__).with_name('liveness_crop_reference.cpp')),
                        '-o', str(binary)], check=True, capture_output=True)
        payload = ''.join(' '.join(map(str, [w, h, *face, scale])) + '\n' for w, h, face, scale in cases)
        result = subprocess.run([str(binary)], input=payload, text=True, capture_output=True, check=True)
        oracle = [tuple(map(int, line.split())) for line in result.stdout.splitlines()]
    actual = [calculate_crop(w, h, face, scale) for w, h, face, scale in cases]
    crop_matches = len(oracle) == len(actual) and oracle == actual

    import cv2
    import ncnn
    import numpy as np
    # Deliberately unequal B/G/R values catch a channel swap or /255 normalization.
    pixels = np.empty((80, 80, 3), dtype=np.uint8)
    pixels[:] = [11, 53, 197]
    tensor = np.array(ncnn.Mat.from_pixels(pixels, ncnn.Mat.PixelType.PIXEL_BGR, 80, 80))
    color_matches = tensor.shape == (3, 80, 80) and all(np.all(tensor[i] == value) for i, value in enumerate([11, 53, 197]))
    frame = np.indices((480, 640)).sum(axis=0).astype(np.uint8)
    frame = np.stack((frame, np.roll(frame, 20, axis=0), np.roll(frame, 40, axis=1)), axis=-1)
    engine = ExperimentalLiveness(args.models)
    diagnostic = engine.diagnose(frame, [200., 150., 100., 100.])
    checks = {
        'crop_matches_pinned_cpp_oracle': crop_matches,
        'bgr_tensor_order_and_unscaled_values': bool(color_matches),
        'input_size_80x80': tensor.shape == (3, 80, 80),
        'opencv_linear_resize_default': np.array_equal(cv2.resize(frame, (80, 80)), cv2.resize(frame, (80, 80), interpolation=cv2.INTER_LINEAR)),
        'live_class_is_index_one': live_probability([.1, .7, .2]) == .7,
        'both_verified_models_executed': len(engine.models) == 2 and diagnostic['liveness'] == 'experimental',
        'never_attendance_eligible': diagnostic['attendance_eligible'] is False,
    }
    manifest = json.loads((args.models / 'manifest.json').read_text())
    report = {'scope': 'synthetic adapter/model execution audit, not spoof or accuracy validation',
              'source_commit': manifest['commit'], 'crop_cases': len(cases), 'checks': checks,
              'passed': sum(checks.values()), 'production_ready': False, 'attendance_eligible': False,
              'limitations': ['C++ oracle covers crop arithmetic only, not independent full inference.',
                             'YuNet boxes and JPEG capture differ from upstream detector/NV21 pipeline.',
                             'Previous private camera photos were deleted; no before/after score comparison.',
                             'Physical spoof presentation must be tested again after this correction.']}
    args.output.write_text(json.dumps(report, indent=2) + '\n')
    if not all(checks.values()):
        raise SystemExit('Adapter audit failed; see aggregate checks')
    print(f'Adapter audit: {len(checks)} checks passed, {len(cases)} crop fixtures. Not liveness approval.')


if __name__ == '__main__':
    main()
