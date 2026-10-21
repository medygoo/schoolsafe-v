"""Install pinned Gitleaks/Trivy into this tools directory; never modify PATH globally."""
from pathlib import Path
import hashlib,urllib.request,platform,tarfile,zipfile,io,os
ROOT=Path(__file__).resolve().parent
WINDOWS=platform.system()=='Windows'
if platform.machine().lower() not in ['amd64','x86_64']:raise SystemExit('Only qualified x64 platforms are supported')
FILES={
 'gitleaks':('https://github.com/gitleaks/gitleaks/releases/download/v8.30.1/',
             'gitleaks_8.30.1_windows_x64.zip' if WINDOWS else 'gitleaks_8.30.1_linux_x64.tar.gz',
             'd29144deff3a68aa93ced33dddf84b7fdc26070add4aa0f4513094c8332afc4e' if WINDOWS else '551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb'),
 'trivy':('https://github.com/aquasecurity/trivy/releases/download/v0.76.0/',
          'trivy_0.76.0_windows-64bit.zip' if WINDOWS else 'trivy_0.76.0_Linux-64bit.tar.gz',
          '7b4762a5a5326cb4b83dfd2bb5d9aaf15a1ed867c75cad7dd8b38840a28e7be6' if WINDOWS else 'e24b1c51293c1b15e4ba8d45bbc7891ba053f90278ec2698f3562c993e145fe7')}
(ROOT/'bin').mkdir(exist_ok=True)
for name,(base,asset,expected) in FILES.items():
    with urllib.request.urlopen(base+asset,timeout=120) as response:data=response.read(120*1024*1024)
    if hashlib.sha256(data).hexdigest()!=expected:raise SystemExit('Archive SHA256 mismatch: '+name)
    binary=name+('.exe' if WINDOWS else '')
    if WINDOWS:
        with zipfile.ZipFile(io.BytesIO(data)) as archive:content=archive.read(binary)
    else:
        with tarfile.open(fileobj=io.BytesIO(data),mode='r:gz') as archive:content=archive.extractfile(binary).read()
    destination=ROOT/'bin'/binary;destination.write_bytes(content);destination.chmod(0o755)
    print(name+': pinned archive SHA256 verified')
