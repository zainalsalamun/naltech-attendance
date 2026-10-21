import json
from pathlib import Path
import tempfile
import subprocess
import sys
import unittest

from evaluate_liveness import coverage, evaluate, load_manifest
from verifier import InvalidFaceImage


class EvaluationTest(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory()
        self.addCleanup(self.folder.cleanup)
        self.root = Path(self.folder.name)
        self.manifest = self.root / 'manifest.json'

    def sample(self, index, kind='live', split='development', subject=None, session=None):
        # Fixture bytes test orchestration, not decoding or real biometric inference.
        name = f'{index}.jpg'
        (self.root / name).write_bytes(str(index).encode())
        return {'path': name, 'subject_id': subject or f'person-{index}',
                'session_id': session or f'session-{index}', 'device_id': 'phone-a',
                'lighting': 'indoor', 'kind': kind, 'split': split}

    def write(self, rows):
        self.manifest.write_text(json.dumps({'version': 1, 'samples': rows}))
        return load_manifest(self.manifest)

    def test_subject_leakage_duplicate_content_and_session_relabel_are_rejected(self):
        first = self.sample(1, subject='same')
        second = self.sample(2, split='holdout', subject='same')
        with self.assertRaisesRegex(ValueError, 'leakage'):
            self.write([first, second])
        second['split'], second['subject_id'] = 'development', 'other'
        (self.root / second['path']).write_bytes(b'1')
        with self.assertRaisesRegex(ValueError, 'Duplicate'):
            self.write([first, second])
        (self.root / second['path']).write_bytes(b'2')
        second['session_id'] = first['session_id']
        with self.assertRaisesRegex(ValueError, 'Session metadata'):
            self.write([first, second])

    def test_paths_cannot_escape_private_dataset_even_via_symlinks(self):
        row = self.sample(1)
        row['path'] = '../outside.jpg'
        with self.assertRaises(ValueError):
            self.write([row])
        row['path'] = 'link.jpg'
        (self.root / 'link.jpg').symlink_to(Path(__file__).resolve())
        with self.assertRaises(ValueError):
            self.write([row])

    def test_missing_controls_are_visible_not_success(self):
        result = coverage(self.write([self.sample(1, kind='screen')]))
        self.assertEqual(result['development']['missing_kinds'], ['live', 'print', 'video'])
        self.assertEqual(result['holdout']['missing_kinds'], ['live', 'print', 'screen', 'video'])

    def test_retries_quality_rejections_holdout_and_aggregate_privacy(self):
        samples = self.write([
            self.sample(1, 'screen', subject='attack', session='retry'),
            self.sample(2, 'screen', subject='attack', session='retry'),
            self.sample(3, 'live', split='holdout'),
            self.sample(4, 'print'),
        ])

        class Fixture:
            def __init__(self):
                self.scores = iter([.2, .85, .95, None])

            def liveness_preview(self, _):
                score = next(self.scores)
                if score is None:
                    raise InvalidFaceImage()
                return {'diagnostic_score': score, 'liveness': 'experimental', 'attendance_eligible': False}

        report = evaluate(samples, Fixture(), .8)
        attack = report['results']['development']['screen']
        self.assertEqual(attack['accepted_sample_rate'], .5)
        self.assertEqual(attack['session_any_acceptance_rate'], 1)
        self.assertEqual(report['results']['development']['print']['quality_rejections'], 1)
        self.assertEqual(report['results']['holdout']['live']['accepted_samples'], 1)
        self.assertIsNone(report['development_sweep'][0]['results']['live']['accepted_sample_rate'])
        self.assertFalse(report['production_ready'])
        self.assertFalse(report['attendance_eligible'])
        encoded = json.dumps(report)
        for private in ('subject_id', 'session_id', '1.jpg', str(self.root), 'attack'):
            self.assertNotIn(private, encoded)

    def test_untrusted_scores_and_authorization_claim_abort_evaluation(self):
        samples = self.write([self.sample(1)])
        for score, eligible in [(float('nan'), False), (1.1, False), (True, False), (.9, True)]:
            class Fixture:
                def liveness_preview(self, _):
                    return {'diagnostic_score': score, 'liveness': 'experimental', 'attendance_eligible': eligible}
            with self.assertRaises(RuntimeError):
                evaluate(samples, Fixture(), .8)

    def test_cli_validation_has_no_inference_claim_and_preserves_existing_report(self):
        self.write([self.sample(1)])
        output = self.root / 'report.json'
        command = [sys.executable, str(Path(__file__).with_name('evaluate_liveness.py')),
                   '--manifest', str(self.manifest), '--output', str(output), '--validate-only']
        result = subprocess.run(command, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0)
        self.assertEqual(json.loads(output.read_text())['status'], 'manifest_validated_only')
        original = output.read_bytes()
        result = subprocess.run(command, capture_output=True, text=True)
        self.assertEqual(result.returncode, 2)
        self.assertEqual(output.read_bytes(), original)
        self.assertNotIn(str(self.root), result.stderr)


if __name__ == '__main__':
    unittest.main()
