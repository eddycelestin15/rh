# -*- coding: utf-8 -*-
"""Corrige deux effets de bord de la reecriture automatique des chemins."""
import io, os, re

SEP = chr(92)  # antislash, evite les soucis d'echappement shell
fixed = {'php': 0, 'form': 0, 'helpers': 0}

for base, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in {'vendor', 'PHPMailer', '_archive', '.docker', '.git'}]
    for f in files:
        if not (f.endswith('.php') or f.endswith('.js')):
            continue
        p = os.path.join(base, f)
        s = io.open(p, encoding='utf-8').read()
        o = s

        # 1) APP_ROOT place devant une URL .php : c'est une URL, pas un chemin disque.
        s, n = re.subn(r"APP_ROOT \. '(/[^']*\.php)'",
                       lambda m: "'%s'" % m.group(1).lstrip('/'), s)
        fixed['php'] += n

        # 2) Attribut HTML casse par la reecriture : action=APP_ROOT . '...'
        s, n2 = re.subn(r"""action=APP_ROOT \. '(/[^']*)'""",
                        lambda m: 'action="%s"' % m.group(1).lstrip('/'), s)
        fixed['form'] += n2

        # 3) helpers.php n'a pas bouge : son chemin relatif doit etre recalcule
        #    depuis le nouveau dossier du fichier appelant.
        if "__DIR__ . '/includes/helpers.php'" in s:
            rel = os.path.relpath('includes/helpers.php', base).replace(SEP, '/')
            s = s.replace("__DIR__ . '/includes/helpers.php'", "__DIR__ . '/%s'" % rel)
            fixed['helpers'] += 1

        if s != o:
            io.open(p, 'w', encoding='utf-8').write(s)

print("APP_ROOT devant une URL .php corriges : %d" % fixed['php'])
print("attributs form action corriges        : %d" % fixed['form'])
print("includes helpers.php corriges         : %d" % fixed['helpers'])
