"""Download pinned original anti-spoof-mn3 ONNX outside the product checkout."""
import argparse
import hashlib
import json
from pathlib import Path
import urllib.request

OMZ_COMMIT = '2c9b5c563ab3a202d085a42e2bbfc48f77961f4b'
UPSTREAM_COMMIT = '82a2c26bacff5462c6f9320c34ae0363ccbed497'
MODEL_NAME = 'anti-spoof-mn3.onnx'
MODEL_URL = 'https://storage.openvinotoolkit.org/repositories/open_model_zoo/public/2022.1/anti-spoof-mn3/anti-spoof-mn3.onnx'
MODEL_SIZE = 12270179
MODEL_SHA384 = '6de4534964b723397b3e8c995cadcf43bc007cc2f9930b95ae25f76adccece5d1d4d058d0b15117b9e4a9f758424f92a'


def fetch(directory):
    directory = Path(directory).resolve()
    if directory.is_relative_to(Path(__file__).resolve().parents[2]):
        raise ValueError('Keep model artifacts outside the source checkout')
    directory.mkdir(parents=True, exist_ok=True)
    model = directory / MODEL_NAME
    raw = model.read_bytes() if model.exists() else urllib.request.urlopen(MODEL_URL, timeout=90).read(MODEL_SIZE + 1)
    if len(raw) != MODEL_SIZE or hashlib.sha384(raw).hexdigest() != MODEL_SHA384:
        raise RuntimeError('Model size or pinned Open Model Zoo SHA384 mismatch')
    artifacts = {MODEL_NAME: raw}
    base = f'https://raw.githubusercontent.com/openvinotoolkit/open_model_zoo/{OMZ_COMMIT}/models/public/anti-spoof-mn3/'
    for filename, url in {
        'model.yml': base + 'model.yml',
        'accuracy-check.yml': base + 'accuracy-check.yml',
        'upstream-LICENSE': f'https://raw.githubusercontent.com/kprokofi/light-weight-face-anti-spoofing/{UPSTREAM_COMMIT}/LICENSE',
    }.items():
        artifacts[filename] = urllib.request.urlopen(url, timeout=30).read(1024 * 1024)
    if MODEL_SHA384.encode() not in artifacts['model.yml']:
        raise RuntimeError('Pinned metadata does not describe expected model')
    manifest = {'engine': 'anti-spoof-mn3-original-onnx', 'omz_commit': OMZ_COMMIT,
                'upstream_commit': UPSTREAM_COMMIT, 'model_url': MODEL_URL,
                'sha384': MODEL_SHA384, 'files': {}}
    for name, content in artifacts.items():
        destination = directory / name
        if destination.exists() and destination.read_bytes() != content:
            raise RuntimeError('Refusing to overwrite different model artifacts')
        if not destination.exists():
            with destination.open('xb') as stream:
                stream.write(content)
        manifest['files'][name] = {'sha256': hashlib.sha256(content).hexdigest()}
    (directory / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print('Original MN3 ONNX verified against pinned SHA384; weights remain outside checkout.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--directory', required=True, type=Path)
    fetch(parser.parse_args().directory)
