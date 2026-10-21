"""Offline experimental evaluation. Never produces an attendance authorization."""
import argparse
import base64
from collections import Counter
import hashlib
import json
import math
from pathlib import Path
import statistics
import time

KINDS = ('live', 'print', 'screen', 'video')
SPLITS = ('development', 'holdout')
FIELDS = {'path', 'subject_id', 'session_id', 'device_id', 'lighting', 'kind', 'split'}


def load_manifest(path):
    path = Path(path).resolve()
    data = json.loads(path.read_text())
    if not isinstance(data, dict) or set(data) != {'version', 'samples'} or data['version'] != 1:
        raise ValueError('Invalid manifest version or fields')
    if not isinstance(data['samples'], list) or not data['samples']:
        raise ValueError('No samples supplied')
    samples, seen, subjects, sessions = [], set(), {}, {}
    for row in data['samples']:
        if not isinstance(row, dict) or set(row) != FIELDS:
            raise ValueError('Invalid sample fields')
        if any(not isinstance(value, str) or not value.strip() for value in row.values()):
            raise ValueError('Sample fields must be nonempty strings')
        if row['kind'] not in KINDS or row['split'] not in SPLITS:
            raise ValueError('Invalid kind or split')
        photo = (path.parent / row['path']).resolve()
        if not photo.is_relative_to(path.parent) or not photo.is_file():
            raise ValueError('Photo must exist inside the private manifest directory')
        if photo.suffix.lower() not in ('.jpg', '.jpeg', '.png') or not 0 < photo.stat().st_size <= 2 * 1024 * 1024:
            raise ValueError('Photo must be JPEG/PNG, at most 2 MB')
        raw = photo.read_bytes()
        digest = hashlib.sha256(raw).digest()
        if digest in seen:
            raise ValueError('Duplicate photo content; do not reuse frames')
        seen.add(digest)
        previous = subjects.setdefault(row['subject_id'], row['split'])
        if previous != row['split']:
            raise ValueError('Subject leakage between development and holdout')
        identity = tuple(row[key] for key in ('subject_id', 'kind', 'split', 'device_id', 'lighting'))
        if sessions.setdefault(row['session_id'], identity) != identity:
            raise ValueError('Session metadata must remain consistent')
        samples.append({**row, 'photo': photo})
    return samples


def coverage(samples):
    result = {}
    for split in SPLITS:
        rows = [row for row in samples if row['split'] == split]
        result[split] = {
            'samples': dict(Counter(row['kind'] for row in rows)),
            'sessions': {kind: len({row['session_id'] for row in rows if row['kind'] == kind}) for kind in KINDS},
            'subjects': len({row['subject_id'] for row in rows}),
            'devices': len({row['device_id'] for row in rows}),
            'lighting_conditions': len({row['lighting'] for row in rows}),
            'missing_kinds': [kind for kind in KINDS if not any(row['kind'] == kind for row in rows)],
        }
    return result


def metrics(rows, threshold):
    result = {}
    for kind in KINDS:
        selected = [row for row in rows if row['kind'] == kind]
        scored = [row for row in selected if row['score'] is not None]
        accepted = sum(row['score'] >= threshold for row in scored)
        sessions = {}
        for row in selected:
            sessions.setdefault(row['session_id'], []).append(row)
        # A replay can retry frames. Report any accepted frame per attack session.
        session_accepts = sum(any(row['score'] is not None and row['score'] >= threshold for row in group)
                              for group in sessions.values())
        result[kind] = {
            'samples': len(selected), 'scored': len(scored),
            'quality_rejections': sum(row['quality_rejected'] for row in selected),
            'accepted_samples': accepted,
            'accepted_sample_rate': accepted / len(selected) if selected else None,
            'sessions': len(sessions), 'sessions_with_any_acceptance': session_accepts,
            'session_any_acceptance_rate': session_accepts / len(sessions) if sessions else None,
        }
    return result


def evaluate(samples, verifier, threshold):
    from verifier import InvalidFaceImage
    rows, elapsed = [], []
    for sample in samples:
        started = time.perf_counter()
        quality_rejected, score = False, None
        try:
            result = verifier.liveness_preview(base64.b64encode(sample['photo'].read_bytes()).decode('ascii'))
            score = result.get('diagnostic_score')
            if (result.get('liveness') != 'experimental' or result.get('attendance_eligible') is not False
                    or isinstance(score, bool) or not isinstance(score, (float, int))
                    or not math.isfinite(score) or not 0 <= score <= 1):
                raise RuntimeError('Invalid experimental provider response')
        except InvalidFaceImage:
            quality_rejected = True
        elapsed.append((time.perf_counter() - started) * 1000)
        rows.append({**sample, 'score': score, 'quality_rejected': quality_rejected})
    # Sweep development only. Holdout is evaluated once at the preselected threshold.
    development = [row for row in rows if row['split'] == 'development']
    return {
        'version': 1, 'status': 'experimental_evaluation_only',
        'attendance_eligible': False, 'production_ready': False,
        'threshold': threshold, 'coverage': coverage(samples),
        'results': {split: metrics([row for row in rows if row['split'] == split], threshold) for split in SPLITS},
        'development_sweep': [{'threshold': value, 'results': metrics(development, value)} for value in (.5, .6, .7, .8, .9, .95)],
        'latency_ms': {'median': round(statistics.median(elapsed), 2), 'maximum': round(max(elapsed), 2)},
        'limits': ['Human labels are not independently attested.',
                   'Frames within a session are correlated; sample counts are not independent trials.',
                   'Missing categories and small cohorts cannot establish spoof resistance.',
                   'Single-frame diagnostic does not validate camera origin or defeat injection.',
                   'No threshold is automatically promoted to production.'],
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--manifest', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--threshold', type=float, default=.8)
    parser.add_argument('--validate-only', action='store_true')
    args = parser.parse_args()
    try:
        if not math.isfinite(args.threshold) or not 0 <= args.threshold <= 1:
            raise ValueError('Threshold must be between zero and one')
        output = Path(args.output).resolve()
        if output.exists():
            raise ValueError('Output already exists; use a new run filename')
        samples = load_manifest(args.manifest)
        if output == Path(args.manifest).resolve() or output in {row['photo'] for row in samples}:
            raise ValueError('Output must not overwrite inputs')
        if args.validate_only:
            report = {'status': 'manifest_validated_only', 'coverage': coverage(samples),
                      'attendance_eligible': False, 'production_ready': False}
        else:
            from verifier import Verifier
            report = evaluate(samples, Verifier(), args.threshold)
        # Aggregate output only: no photos, embeddings, filenames or subject identifiers.
        with output.open('x') as stream:
            json.dump(report, stream, indent=2, allow_nan=False)
            stream.write('\n')
        print('Aggregate report written; attendance remains disabled.')
    except Exception:
        # Exceptions from image/JSON parsers can contain private paths or payloads.
        parser.exit(2, 'Evaluation failed; check private inputs, model configuration and output location. No report produced.\n')


if __name__ == '__main__':
    main()
