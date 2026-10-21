import unittest

from experimental_liveness import calculate_crop, live_probability


class PreprocessingTest(unittest.TestCase):
    def test_crop_golden_vectors_include_odd_size_and_image_edges(self):
        # Numeric expectations checked against the pinned C++ crop oracle.
        cases = [
            ((640, 480, [600., 400., 82., 82.], 2.7), (419, 259, 221, 221)),
            ((640, 480, [-8., -4., 82., 82.], 2.7), (0, 0, 221, 221)),
            ((640, 480, [200., 150., 100., 100.], 2.7), (115, 65, 270, 270)),
            ((321, 321, [100., 100., 300., 300.], 4.), (0, 0, 320, 320)),
        ]
        for arguments, expected in cases:
            with self.subTest(arguments=arguments):
                self.assertEqual(calculate_crop(*arguments), expected)

    def test_crop_rejects_invalid_geometry_and_nonfinite_input(self):
        for frame_w, frame_h, face, scale in [
            (1, 480, [0, 0, 80, 80], 2.7),
            (640, 480, [0, 0, 0, 80], 2.7),
            (640, 480, [float('nan'), 0, 80, 80], 2.7),
            (640, 480, [0, 0, 80, 80], float('inf')),
            (640, 480, [0, 0, 80, 80], -1),
        ]:
            with self.assertRaises(RuntimeError):
                calculate_crop(frame_w, frame_h, face, scale)

    def test_live_class_and_invalid_probability_vectors(self):
        self.assertEqual(live_probability([.1, .7, .2]), .7)
        for values in [[-.1, .9, .2], [0, 1.1, -.1], [0, float('nan'), 1],
                       [0, float('inf'), 1], [0, 0, 0], [.5, .5], [False, 1, 0]]:
            with self.assertRaises(RuntimeError):
                live_probability(values)


if __name__ == '__main__':
    unittest.main()
