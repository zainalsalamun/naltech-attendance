"""Numerical smoke test with an operator-supplied, consented/public fixture."""
import argparse
import base64
from io import BytesIO
import json
from pathlib import Path

from PIL import Image, ImageEnhance
from verifier import Verifier, InvalidFaceImage


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--reference', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    engine = Verifier()
    raw = args.reference.read_bytes()
    encoded = base64.b64encode(raw).decode()
    same = engine.compare(encoded, encoded)
    with Image.open(BytesIO(raw)) as original:
        image = original.convert('RGB').resize((512, 512))
    changed = BytesIO()
    ImageEnhance.Brightness(image).enhance(.85).save(changed, format='JPEG')
    adjusted = engine.compare(encoded, base64.b64encode(changed.getvalue()).decode())
    checks = {
        'models_loaded': True, 'same_image_compared': same['similarity'] > .9,
        'brightness_variant_compared': adjusted['similarity'] > .9,
        'same_image_not_attendance_eligible': same['attendance_eligible'] is False,
        'liveness_not_claimed': same['liveness'] == 'not_configured',
    }
    blank = BytesIO()
    Image.new('RGB', (480, 640), 'white').save(blank, format='JPEG')
    pair = Image.new('RGB', (1024, 512))
    pair.paste(image, (0, 0))
    pair.paste(image, (512, 0))
    multiple = BytesIO()
    pair.save(multiple, format='JPEG')
    for name, payload in [('blank_rejected', base64.b64encode(blank.getvalue()).decode()),
                          ('multiple_faces_rejected', base64.b64encode(multiple.getvalue()).decode()),
                          ('malformed_base64_rejected', '****')]:
        try:
            engine.compare(encoded, payload)
            checks[name] = False
        except InvalidFaceImage:
            checks[name] = True
    report = {'scope': 'local model smoke only; not identity accuracy or liveness validation',
              'checks': checks, 'passed': sum(checks.values()),
              'limitations': ['No different-person accuracy benchmark', 'No real camera or spoof presentation test',
                              'Liveness not implemented; attendance remains blocked']}
    args.output.write_text(json.dumps(report, indent=2) + '\n')
    if not all(checks.values()):
        raise SystemExit('Model smoke failed; no biometric details printed')
    print(f'Actual model smoke checks passed: {len(checks)}')


if __name__ == '__main__':
    main()
