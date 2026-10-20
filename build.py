# -*- coding: utf-8 -*-
"""Build the RK add-ons for JComments package from src/.

    python build.py            -> dist/pkg_rkjcomments-<version>.zip (+ dist/packages/*.zip, dist/SHA256SUMS.txt)

Before packing: PHP lint of every .php, a JavaScript syntax check (node, when available), a scan for
site-specific literals and leftover non-English text outside the language files, a CRLF check, a check that
every language key used in a manifest or PHP file exists in that extension's en-GB .ini files, and a check
that every manifest carries the package version.
"""
import hashlib
import os
import re
import shutil
import subprocess
import sys
import zipfile

sys.stdout.reconfigure(encoding='utf-8')

ROOT = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(ROOT, 'src')
PKG = os.path.join(ROOT, 'pkg')
DIST = os.path.join(ROOT, 'dist')
PHP = next((p for p in [
    r'C:\laragon\bin\php\php-8.5.5-nts-Win32-vs17-x64\php.exe', 'php',
] if p == 'php' or os.path.isfile(p)), 'php')

# extension folder -> (manifest file name, language key prefix)
EXTENSIONS = {
    'plg_system_rkjccount': ('rkjccount.xml', 'PLG_SYSTEM_RKJCCOUNT'),
    'plg_jcomments_rkavatar': ('rkavatar.xml', 'PLG_JCOMMENTS_RKAVATAR'),
    'plg_jcomments_rkreactions': ('rkreactions.xml', 'PLG_JCOMMENTS_RKREACTIONS'),
    'plg_jcomments_rkpick': ('rkpick.xml', 'PLG_JCOMMENTS_RKPICK'),
    'plg_jcomments_rkbadges': ('rkbadges.xml', 'PLG_JCOMMENTS_RKBADGES'),
    'plg_jcomments_rkvoice': ('rkvoice.xml', 'PLG_JCOMMENTS_RKVOICE'),
    'plg_jcomments_rkpurge': ('rkpurge.xml', 'PLG_JCOMMENTS_RKPURGE'),
    'com_rkjcomments': ('rkjcomments.xml', 'COM_RKJCOMMENTS'),
    'file_rkjcomments': ('files_rkjcomments.xml', 'FILES_RKJCOMMENTS'),
}
TEXT = ('.php', '.ini', '.xml', '.md', '.css', '.js', '.sql')
FORBIDDEN = re.compile(r'gag5f_|\bdragan\b|\bdjura\b|\bsrba\b|\bgagi\b|it_medical|laragon|sanctum|H:[\\/]API|D:\\projekti', re.I)
DIACRITICS = re.compile('[čćšđžČĆŠĐŽ]')
EMOJI_ENTRY = re.compile(r"'[^'|]+\|[^']*\|[^']*'")


def files(base):
    for folder, dirs, names in os.walk(base):
        dirs.sort()
        for name in sorted(names):
            path = os.path.join(folder, name)
            yield path, os.path.relpath(path, base).replace(os.sep, '/')


def ini_keys(ext):
    keys = set()
    for path, rel in files(os.path.join(SRC, ext)):
        if '/en-GB/' in rel and rel.endswith('.ini'):
            for line in open(path, encoding='utf-8'):
                if '=' in line and not line.startswith(';'):
                    keys.add(line.split('=', 1)[0].strip())
    return keys


def check():
    problems = []
    version = re.search(r'<version>([^<]+)</version>', open(os.path.join(PKG, 'pkg_rkjcomments.xml'), encoding='utf-8').read()).group(1)

    for base in (SRC, PKG, os.path.join(ROOT, 'extras')):
        for path, rel in files(base):
            if rel.endswith('.php'):
                r = subprocess.run([PHP, '-l', path], capture_output=True, text=True)
                if r.returncode != 0:
                    problems.append(f'lint: {rel}: {r.stdout.strip()} {r.stderr.strip()}')
            if rel.endswith(TEXT):
                raw = open(path, 'rb').read()
                if b'\r\n' in raw:
                    problems.append(f'crlf: {rel}')
                text = raw.decode('utf-8')
                for m in FORBIDDEN.finditer(text):
                    problems.append(f'literal: {rel}: {m.group(0)!r}')
                if '/sr-YU/' not in rel and not rel.endswith('.md'):
                    for n, line in enumerate(text.split('\n'), 1):
                        if DIACRITICS.search(line) and 'Radnička Klasa' not in line and not EMOJI_ENTRY.search(line):
                            problems.append(f'non-english: {rel}:{n}: {line.strip()[:70]}')

    for ext, (manifest, prefix) in EXTENSIONS.items():
        base = os.path.join(SRC, ext)
        xml = open(os.path.join(base, manifest), encoding='utf-8').read()
        v = re.search(r'<version>([^<]+)</version>', xml).group(1)
        if v != version:
            problems.append(f'version: {ext} is {v}, package is {version}')
        keys = ini_keys(ext)
        used = set(re.findall(prefix + r'[A-Z0-9_]*', xml))
        for path, rel in files(base):
            if rel.endswith('.php'):
                used.update(re.findall(prefix + r'_[A-Z0-9_]+', open(path, encoding='utf-8').read()))
        for key in sorted(k for k in used - keys if not k.endswith('_')):
            problems.append(f'language: {ext} uses {key} which no en-GB .ini defines')

    js = os.path.join(SRC, 'file_rkjcomments', 'tpl', 'rk-emoji.js')
    node = shutil.which('node')
    if node:
        r = subprocess.run([node, '-e', "new Function(require('fs').readFileSync(process.argv[1],'utf8'))", js], capture_output=True, text=True)
        if r.returncode != 0:
            problems.append(f'js: rk-emoji.js: {r.stderr.strip()[:200]}')
    return version, problems


def zip_dir(base, out):
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        for path, rel in files(base):
            z.write(path, rel)


def build(version):
    packages = os.path.join(DIST, 'packages')
    shutil.rmtree(packages, ignore_errors=True)
    os.makedirs(packages)
    for ext in EXTENSIONS:
        zip_dir(os.path.join(SRC, ext), os.path.join(packages, ext.replace('file_', 'files_') + '.zip'))
    out = os.path.join(DIST, f'pkg_rkjcomments-{version}.zip')
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        z.write(os.path.join(PKG, 'pkg_rkjcomments.xml'), 'pkg_rkjcomments.xml')
        z.write(os.path.join(PKG, 'script.php'), 'script.php')
        for name in sorted(os.listdir(packages)):
            z.write(os.path.join(packages, name), 'packages/' + name)
    star = os.path.join(DIST, f'rkjcomments-admin-star-{version}.zip')
    zip_dir(os.path.join(ROOT, 'extras', 'admin-star'), star)
    sums = []
    for path in (out, star):
        sums.append(hashlib.sha256(open(path, 'rb').read()).hexdigest() + '  ' + os.path.basename(path))
    open(os.path.join(DIST, 'SHA256SUMS.txt'), 'w', newline='\n').write('\n'.join(sums) + '\n')
    return out, star, sums


if __name__ == '__main__':
    version, problems = check()
    if problems:
        print('\n'.join(problems))
        sys.exit(1)
    os.makedirs(DIST, exist_ok=True)
    out, star, sums = build(version)
    for s in sums:
        print(s)
    print('ok', os.path.relpath(out, ROOT), os.path.getsize(out) // 1024, 'KB;', os.path.relpath(star, ROOT), os.path.getsize(star) // 1024, 'KB')
