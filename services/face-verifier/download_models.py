"""Fetch pinned official OpenCV Zoo PoC models outside the source tree."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import urllib.request

COMMIT = "47534e27c9851bb1128ccc0102f1145e27f23f98"
MODELS = {
    "yunet": "models/face_detection_yunet/face_detection_yunet_2023mar.onnx",
    "sface": "models/face_recognition_sface/face_recognition_sface_2021dec.onnx",
}


def fetch(directory):
    directory.mkdir(parents=True, exist_ok=True)
    manifest = {"repository": "opencv/opencv_zoo", "commit": COMMIT, "models": {}}
    for key, relative in MODELS.items():
        raw = f"https://raw.githubusercontent.com/opencv/opencv_zoo/{COMMIT}/{relative}"
        pointer = urllib.request.urlopen(raw, timeout=30).read().decode()
        digest = re.search(r"oid sha256:([a-f0-9]{64})", pointer)
        size = re.search(r"size (\d+)", pointer)
        if not digest or not size:
            raise RuntimeError("Expected a pinned Git LFS pointer")
        destination = directory / Path(relative).name
        expected_hash = digest.group(1)
        if not destination.exists():
            temporary = destination.with_suffix(destination.suffix + ".part")
            subprocess.run(["curl", "--fail", "--silent", "--show-error", "--location",
                            "--retry", "2", "--continue-at", "-", "--max-time", "600",
                            "--output", str(temporary),
                            f"https://media.githubusercontent.com/media/opencv/opencv_zoo/{COMMIT}/{relative}"], check=True)
            if temporary.stat().st_size != int(size.group(1)) or hashlib.sha256(temporary.read_bytes()).hexdigest() != expected_hash:
                raise RuntimeError("Downloaded model does not match pinned LFS checksum")
            temporary.rename(destination)
        if hashlib.sha256(destination.read_bytes()).hexdigest() != expected_hash:
            raise RuntimeError("Existing model checksum mismatch; refusing to overwrite")
        manifest["models"][key] = {"filename": destination.name, "sha256": expected_hash}
        license_file = directory / f"{key}.LICENSE"
        license_file.write_bytes(urllib.request.urlopen(raw.rsplit("/", 1)[0] + "/LICENSE", timeout=30).read())
    (directory / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n")
    print("Pinned PoC models and upstream licenses downloaded; not bundled into the product.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--directory", required=True, type=Path)
    fetch(parser.parse_args().directory)
