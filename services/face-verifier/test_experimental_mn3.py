import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

from experimental_mn3 import ExperimentalMn3, MEAN, STD, mn3_crop, mn3_live_probability, prepare_input
from verifier import InvalidFaceImage

HAS_RUNTIME = importlib.util.find_spec('cv2') is not None and importlib.util.find_spec('numpy') is not None


class Mn3Test(unittest.TestCase):
    def test_downloader_rejects_checkout_and_corrupt_existing_model(self):
        from download_mn3 import MODEL_NAME, fetch
        forbidden = Path(__file__).resolve().parent / '.forbidden-mn3-test'
        self.assertFalse(forbidden.exists())
        with self.assertRaises(ValueError):
            fetch(forbidden)
        self.assertFalse(forbidden.exists())
        with tempfile.TemporaryDirectory() as folder:
            model = Path(folder) / MODEL_NAME
            model.write_bytes(b'corrupt-fixture')
            with self.assertRaisesRegex(RuntimeError, 'SHA384'):
                fetch(folder)
            self.assertEqual(model.read_bytes(), b'corrupt-fixture')
            self.assertFalse(Path(folder, 'manifest.json').exists())

    def test_crop_expansion_clipping_and_invalid_geometry(self):
        self.assertEqual(mn3_crop(640, 480, [200, 150, 100, 100]), (195, 147, 305, 252))
        self.assertEqual(mn3_crop(640, 480, [0, 0, 640, 480]), (0, 0, 640, 480))
        for face in [[1, 1, 0, 80], [float('nan'), 1, 80, 80], [700, 600, 80, 80]]:
            with self.assertRaises(InvalidFaceImage):
                mn3_crop(640, 480, face)

    def test_live_is_class_zero_and_invalid_vectors_fail_closed(self):
        self.assertEqual(mn3_live_probability([.9, .1]), .9)
        self.assertEqual(mn3_live_probability([.1, .9]), .1)
        for values in [[float('nan'), 1], [-.1, 1.1], [False, 1], [.9, .9], [.1, .2, .7]]:
            with self.assertRaises(RuntimeError):
                mn3_live_probability(values)

    @unittest.skipUnless(HAS_RUNTIME, 'Install the existing core requirements in a private venv')
    def test_original_onnx_rgb_normalization_shape_and_dtype(self):
        import numpy as np
        frame = np.full((480, 640, 3), [11, 53, 197], dtype=np.uint8)
        blob = prepare_input(frame, [200, 150, 100, 100])
        self.assertEqual(blob.shape, (1, 3, 128, 128))
        self.assertEqual(blob.dtype, np.float32)
        self.assertTrue(blob.flags.c_contiguous)
        expected = [(value - mean) / scale for value, mean, scale in zip([197, 53, 11], MEAN, STD)]
        for channel, value in enumerate(expected):
            np.testing.assert_allclose(blob[0, channel], value, rtol=1e-6)

    @unittest.skipUnless(HAS_RUNTIME, 'Install the existing core requirements in a private venv')
    def test_inference_contract_never_returns_attendance_proof(self):
        import numpy as np

        class FixtureNet:
            def setInput(self, blob, name):
                self.input_name, self.shape = name, blob.shape

            def forward(self, name):
                self.output_name = name
                return np.array([[.9, .1]], dtype=np.float32)

        model = ExperimentalMn3.__new__(ExperimentalMn3)
        model.net = FixtureNet()
        result = model.diagnose(np.full((480, 640, 3), 127, dtype=np.uint8), [200, 150, 100, 100])
        self.assertEqual(model.net.input_name, 'actual_input_1')
        self.assertEqual(model.net.output_name, 'output1')
        self.assertTrue(result['candidate_live'])
        self.assertFalse(result['attendance_eligible'])
        self.assertEqual(result['liveness'], 'experimental')

    @unittest.skipUnless(HAS_RUNTIME, 'Install the existing core requirements in a private venv')
    def test_incomplete_manifest_cannot_load_any_model(self):
        from download_mn3 import OMZ_COMMIT
        with tempfile.TemporaryDirectory() as folder:
            Path(folder, 'manifest.json').write_text(json.dumps({
                'engine': 'anti-spoof-mn3-original-onnx', 'omz_commit': OMZ_COMMIT, 'files': {},
            }))
            with self.assertRaisesRegex(RuntimeError, 'Incomplete'):
                ExperimentalMn3(folder)


if __name__ == '__main__':
    unittest.main()
