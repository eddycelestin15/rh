# -*- coding: utf-8 -*-
"""
Remplace les « header('Location: ...') » relatifs par rediriger().

Depuis un sous-dossier, un Location relatif est resolu par le navigateur
contre l'URL courante : auth/auth_process.php emettant « Location: index.php »
envoyait vers /auth/index.php (404). rediriger() prefixe par BASE_URL.
"""
import io, os, re

LOC = re.compile(
    r"""header\(\s*(["'])\s*Location\s*:\s*(?P<corps>.*?)\1\s*\)\s*;"""
    r"""(?P<suite>\s*\r?\n\s*exit(?:\(\))?\s*;)?""",
    re.S)

changed = []
for base, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in {'vendor', 'PHPMailer', '_archive', '.docker', '.git'}]
    for f in sorted(files):
        if not f.endswith('.php'):
            continue
        p = os.path.join(base, f).replace(chr(92), '/').lstrip('./')
        # check_session.php construit deja un chemin absolu : on le laisse
        if p.endswith('includes/check_session.php'):
            continue

        s = io.open(p, encoding='utf-8').read()
        o = s
        n = 0

        def sub(m):
            global n
            corps = m.group('corps').strip()
            # deja absolu (http..., / au debut, ou concatenation avec BASE_URL)
            if corps.startswith(('http', '/')) or 'BASE_URL' in corps:
                return m.group(0)
            q = m.group(1)
            n += 1
            return "rediriger(%s%s%s);" % (q, corps, q)

        s = LOC.sub(sub, s)
        if n:
            # bootstrap.php fournit rediriger()
            if 'bootstrap.php' not in s:
                rel = os.path.relpath('includes/bootstrap.php', os.path.dirname(p) or '.').replace(chr(92), '/')
                s = re.sub(r'^(<\?php[ \t]*\r?\n)',
                           r"\1require_once __DIR__ . '/%s';" % rel + chr(10),
                           s, count=1)
            io.open(p, 'w', encoding='utf-8').write(s)
            changed.append((p, n))

print('Redirections corrigees :')
for p, n in changed:
    print('   %-44s %d' % (p, n))
print('total : %d redirections dans %d fichiers' % (sum(n for _, n in changed), len(changed)))
