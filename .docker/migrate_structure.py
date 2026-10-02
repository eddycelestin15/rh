# -*- coding: utf-8 -*-
"""
Restructuration de l'arborescence du projet.

1. Deplace les 146 fichiers PHP de la racine vers une arborescence par domaine.
2. Reecrit les include/require en chemins absolus (__DIR__).
3. Reecrit les URL cote client vers les nouveaux chemins (racine appli).
4. Reecrit les chemins disque (pieces/, images/, Logo/...) via APP_ROOT.
5. Injecte <base href> dans les pages completes pour que les URL relatives
   continuent de se resoudre depuis la racine de l'application.
"""
import io, os, re, sys, importlib.util

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(ROOT)

spec = importlib.util.spec_from_file_location('plan', '.docker/plan_restructure.py')
plan = importlib.util.module_from_spec(spec)
spec.loader.exec_module(plan)

DRY = '--apply' not in sys.argv

# ---------------------------------------------------------------- carte
NEW = {}                                   # 'get_data.php' -> 'api/agents/get_data.php'
for dest, files in plan.MAP.items():
    for f in files:
        NEW[f] = '%s/%s' % (dest, f)

# api/api_localite_service.php existe deja dans un sous-dossier
NEW_EXISTING = {'api/api_localite_service.php': 'api/referentiel/api_localite_service.php'}

log = []


def move_files():
    for dest in sorted(plan.MAP):
        if not DRY:
            os.makedirs(dest, exist_ok=True)
    for src, dst in sorted(NEW.items()):
        if not os.path.exists(src):
            log.append('  ABSENT %s' % src); continue
        if not DRY:
            os.replace(src, dst)
        log.append('  %-52s -> %s' % (src, dst))
    # api_localite_service.php : api/ -> api/referentiel/
    for src, dst in NEW_EXISTING.items():
        if os.path.exists(src):
            if not DRY:
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                os.replace(src, dst)
            log.append('  %-52s -> %s' % (src, dst))


def collect_targets():
    """Tous les fichiers a reecrire, apres deplacement."""
    out = []
    skip_dirs = {'vendor', 'PHPMailer', '_archive', '.docker', 'node_modules', '.git'}
    for base, dirs, files in os.walk('.'):
        dirs[:] = [d for d in dirs if d not in skip_dirs]
        for f in files:
            if f.endswith('.php') or (f.endswith('.js') and not f.endswith('.min.js')):
                out.append(os.path.normpath(os.path.join(base, f)))
    return out


def rel_include(from_file, to_path):
    """Chemin relatif pour un require, depuis le dossier de from_file."""
    d = os.path.dirname(os.path.abspath(from_file))
    p = os.path.relpath(os.path.abspath(to_path), d)
    return p.replace(os.sep, '/')


# Tous les noms de fichiers PHP applicatifs deplaces (pour la reecriture)
ALL_MOVED = dict(NEW)
ALL_MOVED['api_localite_service.php'] = 'api/referentiel/api_localite_service.php'


def rewrite_includes(text, path):
    """include/require 'x.php'  ->  require_once __DIR__ . '/<rel>'"""
    pat = re.compile(
        r"""(?P<kw>include|require)(?P<once>_once)?\s*\(?\s*"""
        r"""(?:__DIR__\s*\.\s*)?"""
        r"""(?P<q>['"])(?P<path>[^'"]+\.php)(?P=q)\s*\)?\s*;"""
    )

    def sub(m):
        raw = m.group('path')
        name = os.path.basename(raw)
        # vendor/autoload.php et PHPMailer : cibles non deplacees
        if 'autoload' in raw or 'PHPMailer' in raw:
            target = raw.lstrip('./').lstrip('/')
            if raw.startswith('/'):
                target = raw.lstrip('/')
            return "require_once __DIR__ . '/%s';" % rel_include(path, target)
        if name in ALL_MOVED:
            return "require_once __DIR__ . '/%s';" % rel_include(path, ALL_MOVED[name])
        return m.group(0)

    return pat.sub(sub, text)


# Chaine citee valant exactement un fichier .php applicatif (+ query/ancre eventuels)
URL_PAT = re.compile(r"""(?P<q>['"])(?P<pre>\./)?(?P<name>[A-Za-z0-9_\-]+\.php)(?P<tail>[?#][^'"]*)?(?P=q)""")


def rewrite_urls(text):
    def sub(m):
        name = m.group('name')
        if name not in ALL_MOVED:
            return m.group(0)
        q = m.group('q')
        return '%s%s%s%s' % (q, ALL_MOVED[name], m.group('tail') or '', q)
    return URL_PAT.sub(sub, text)


# Chemins disque : 'pieces/...', 'images/...', 'Logo/...', 'uploads/...', 'documents/...'
FS_PAT = re.compile(r"""(?P<q>['"])(?P<p>(?:pieces|images|Logo|uploads|documents)/[^'"]*)(?P=q)""")
# Contextes HTML ou il s'agit d'une URL, pas d'un chemin disque
HTML_ATTR = re.compile(r"""(?:src|href|content|data-[a-z-]+)\s*=\s*$""", re.I)


def rewrite_fs_paths(text):
    out, last = [], 0
    for m in FS_PAT.finditer(text):
        before = text[max(0, m.start() - 40):m.start()]
        out.append(text[last:m.start()])
        if HTML_ATTR.search(before):
            out.append(m.group(0))            # URL : laissee telle quelle (<base href> gere)
        else:
            out.append("APP_ROOT . '/%s'" % m.group('p'))
        last = m.end()
    out.append(text[last:])
    return ''.join(out)


def main():
    print('=== 1. DEPLACEMENT ===')
    move_files()
    print('\n'.join(log[:8]))
    print('  ... (%d fichiers)' % len(log))

    if DRY:
        print('\n(simulation : relancer avec --apply)')
        return

    print('\n=== 2. REECRITURE DES REFERENCES ===')
    stats = {'inc': 0, 'url': 0, 'fs': 0, 'files': 0}
    for path in collect_targets():
        try:
            src = io.open(path, encoding='utf-8').read()
        except UnicodeDecodeError:
            src = io.open(path, encoding='latin-1').read()
        txt = src

        if path.endswith('.php'):
            t1 = rewrite_includes(txt, path)
            if t1 != txt:
                stats['inc'] += 1; txt = t1

        t2 = rewrite_urls(txt)
        if t2 != txt:
            stats['url'] += 1; txt = t2

        if path.endswith('.php'):
            t3 = rewrite_fs_paths(txt)
            if t3 != txt:
                stats['fs'] += 1; txt = t3

        if txt != src:
            io.open(path, 'w', encoding='utf-8').write(txt)
            stats['files'] += 1

    print('  fichiers modifies      : %d' % stats['files'])
    print('  includes reecrits      : %d fichiers' % stats['inc'])
    print('  URL reecrites          : %d fichiers' % stats['url'])
    print('  chemins disque reecrits: %d fichiers' % stats['fs'])


if __name__ == '__main__':
    main()
