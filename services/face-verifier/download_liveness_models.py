"""Opt-in upstream MiniFASNet NCNN assets for research, outside the product."""
import argparse
import hashlib
import json
from pathlib import Path
import urllib.request

REPOSITORY = 'minivision-ai/Silent-Face-Anti-Spoofing-APK'
COMMIT = '83329c97ba1153e2bbdd374dee1b5d33a7367dc7'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--directory', required=True, type=Path)
    directory = parser.parse_args().directory
    directory.mkdir(parents=True, exist_ok=True)
    tree = json.load(urllib.request.urlopen(f'https://api.github.com/repos/{REPOSITORY}/git/trees/{COMMIT}?recursive=1', timeout=30))
    files = {entry['path']: entry['sha'] for entry in tree['tree']}
    manifest = {'repository': REPOSITORY, 'commit': COMMIT, 'files': {}}
    for name in ['config.json', 'model_1.param', 'model_1.bin', 'model_2.param', 'model_2.bin', 'LICENSE']:
        relative = name if name == 'LICENSE' else 'engine/src/main/assets/live/' + name
        raw = urllib.request.urlopen(f'https://raw.githubusercontent.com/{REPOSITORY}/{COMMIT}/{relative}', timeout=60).read()
        if hashlib.sha1(f'blob {len(raw)}\0'.encode() + raw).hexdigest() != files[relative]:
            raise RuntimeError('Pinned Git blob checksum mismatch')
        destination = directory / name
        if destination.exists() and destination.read_bytes() != raw:
            raise RuntimeError('Refusing to overwrite a different existing artifact')
        destination.write_bytes(raw)
        manifest['files'][name] = {'sha256': hashlib.sha256(raw).hexdigest()}
    (directory / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print('Pinned experimental assets verified; no production liveness enabled.')


if __name__ == '__main__':
    main()
