"""Coordinate only the explicitly marked disposable leave QA app and backend."""
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time

qa=Path(sys.argv[1]).resolve()
serial=sys.argv[2]
assert (qa/'.naltech-qa').is_file()
root=Path(__file__).resolve().parents[2]
package='com.naltech.attendance.leaveqa'
cache='/data/user/0/'+package+'/cache'
adb=['adb','-s',serial]
def app(*args):
    return subprocess.run(adb+['exec-out','run-as',package,*args],capture_output=True)
end=time.monotonic()+1200
seen=set()
while time.monotonic()<end:
    result=app('cat',cache+'/leave-progress.json')
    if result.returncode:
        time.sleep(2)
        continue
    try:
        progress=json.loads(result.stdout)
    except (ValueError,UnicodeError):
        time.sleep(2)
        continue
    stage=progress.get('stage')
    if stage in seen:
        time.sleep(2)
        continue
    seen.add(stage)
    print('Native stage: '+str(stage),flush=True)
    if stage in ['awaiting_manager','awaiting_hr']:
        phase=stage.removeprefix('awaiting_')
        file=qa/('leave-'+phase+'-progress.json')
        file.write_text(json.dumps(progress))
        env=os.environ.copy()
        env['NODE_PATH']='/Users/macbookpro/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
        subprocess.run(['/Users/macbookpro/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node',str(root/'tools/mobile-qa/leave_device_dashboard.cjs'),str(qa),str(file),phase],check=True,cwd=root,env=env)
        subprocess.run(adb+['shell','run-as',package,'touch',cache+'/control-'+phase],check=True)
    elif stage=='complete':
        output=root/'docs/screenshots/mobile-leave-device'
        output.mkdir(parents=True,exist_ok=True)
        listing=app('ls','-1',cache)
        listing.check_returncode()
        files=[]
        for name in listing.stdout.decode().splitlines():
            if not re.fullmatch(r'leave-[a-z0-9-]+\.png',name):
                continue
            result=app('cat',cache+'/'+name)
            result.check_returncode()
            assert result.stdout.startswith(b'\x89PNG\r\n\x1a\n')
            (output/name).write_bytes(result.stdout)
            files.append(name)
        assert files, 'Native screenshots missing'
        progress['screenshots']=files
        (root/'docs/mobile-leave-device-progress.json').write_text(json.dumps(progress,indent=2)+'\n')
        subprocess.run(adb+['shell','run-as',package,'touch',cache+'/control-finish'],check=True)
        print('Exported native screenshots: '+str(len(files)),flush=True)
        break
else:
    raise RuntimeError('Native coordination timed out')
