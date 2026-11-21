"""Compare OpenCV and ONNX Runtime CPU on identical MN3 tensors; aggregates only."""
import argparse
import base64
from collections import Counter
import json
import os
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'services/face-verifier'))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--matching-models', required=True, type=Path)
    parser.add_argument('--mn3-models', required=True, type=Path)
    parser.add_argument('--manifest', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    if args.output.exists():
        parser.error('Use a new output filename')
    import cv2
    import numpy as np
    import onnxruntime as ort
    from evaluate_liveness import load_manifest
    from experimental_mn3 import MODEL_NAME, mn3_live_probability, prepare_input
    from verifier import Verifier

    matching = json.loads((args.matching_models / 'manifest.json').read_text())
    for name, item in matching['models'].items():
        os.environ['FACE_' + name.upper() + '_MODEL'] = str(args.matching_models / item['filename'])
        os.environ['FACE_' + name.upper() + '_SHA256'] = item['sha256']
    os.environ.update({'FACE_LIVENESS_EXPERIMENTAL_ENABLED': 'true',
                      'FACE_LIVENESS_EXPERIMENTAL_ENGINE': 'mn3',
                      'FACE_MN3_MODEL_DIR': str(args.mn3_models)})
    verifier = Verifier()  # Verifies the original ONNX checksum before either runtime.
    options = ort.SessionOptions()
    options.intra_op_num_threads = options.inter_op_num_threads = 1
    options.log_severity_level = 3
    reference = ort.InferenceSession(str(args.mn3_models / MODEL_NAME), sess_options=options,
                                    providers=['CPUExecutionProvider'])
    assert reference.get_inputs()[0].name == 'actual_input_1'
    assert reference.get_inputs()[0].shape == [1, 3, 128, 128]
    assert reference.get_outputs()[0].name == 'output1'

    deltas, acceptance = [], {'opencv': Counter(), 'onnxruntime': Counter()}
    decisions_equal = True

    def compare(frame, face, kind=None):
        nonlocal decisions_equal
        blob = prepare_input(frame, face)
        net = verifier.experimental_liveness.net
        net.setInput(blob, 'actual_input_1')
        actual = net.forward('output1').reshape(-1)
        expected = reference.run(['output1'], {'actual_input_1': blob})[0].reshape(-1)
        first, second = mn3_live_probability(actual.tolist()), mn3_live_probability(expected.tolist())
        deltas.append(float(np.max(np.abs(actual - expected))))
        decisions_equal = decisions_equal and ((first >= .8) == (second >= .8))
        if kind is not None:
            acceptance['opencv'][kind] += int(first >= .8)
            acceptance['onnxruntime'][kind] += int(second >= .8)

    randomizer = np.random.default_rng(20261021)
    frames = [np.zeros((480, 640, 3), dtype=np.uint8),
              np.full((480, 640, 3), 127, dtype=np.uint8),
              np.full((480, 640, 3), [11, 53, 197], dtype=np.uint8),
              randomizer.integers(0, 256, (480, 640, 3), dtype=np.uint8)]
    for frame in frames:
        compare(frame, [200, 150, 100, 100])
    samples = load_manifest(args.manifest)
    for sample in samples:
        encoded = base64.b64encode(sample['photo'].read_bytes()).decode('ascii')
        compare(*verifier._face(encoded), kind=sample['kind'])
    parity = max(deltas) <= 1e-4 and decisions_equal
    report = {'scope': 'paired runtime inference audit, not liveness certification',
              'engine': 'anti-spoof-mn3-original-onnx', 'threshold': .8,
              'synthetic_samples': len(frames), 'camera_samples': len(samples),
              'camera_counts': dict(Counter(row['kind'] for row in samples)),
              'accepted_by_kind': {key: dict(value) for key, value in acceptance.items()},
              'maximum_probability_delta_between_runtimes': max(deltas),
              'tolerance': 1e-4, 'decisions_equal': decisions_equal, 'parity_passed': parity,
              'runtime_versions': {'opencv': cv2.__version__, 'onnxruntime': ort.__version__, 'numpy': np.__version__},
              'model_provenance': json.loads((args.mn3_models / 'manifest.json').read_text()),
              'production_ready': False, 'attendance_eligible': False,
              'limitations': ['Both runtimes receive the same preprocessing; parity alone cannot validate camera labels/crop policy.',
                             'One person/device, no holdout; do not infer population accuracy.']}
    with args.output.open('x') as stream:
        json.dump(report, stream, indent=2, allow_nan=False)
        stream.write('\n')
    if not parity:
        raise SystemExit('Runtime parity failed; aggregate report saved.')
    print('OpenCV/ONNX Runtime CPU parity passed; aggregate report saved. Attendance remains disabled.')


if __name__ == '__main__':
    try:
        main()
    except Exception:
        raise SystemExit('MN3 audit failed; check private inputs/runtime configuration. No private details printed.') from None
