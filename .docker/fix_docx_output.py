# -*- coding: utf-8 -*-
"""
Securise l'envoi des fichiers .docx dans tous les generateurs.

Un .docx est une archive ZIP : le moindre avertissement PHP emis avant
l'en-tete de telechargement se retrouve en tete du fichier et le corrompt
(Word refuse alors de l'ouvrir).

On insere donc un appel a vider_tampon_sortie() juste avant chaque en-tete
Content-Type qui precede un Content-Disposition: attachment.
Le tampon lui-meme est ouvert par ob_start() en debut de script.
"""
import io, os, re

CALL = 'vider_tampon_sortie();'
patched, already = [], []

for base, dirs, files in os.walk('documents'):
    for f in sorted(files):
        if not f.endswith('.php'):
            continue
        p = os.path.join(base, f)
        s = io.open(p, encoding='utf-8').read()
        o = s

        lines = s.split(chr(10))
        out, i, n = [], 0, 0
        while i < len(lines):
            line = lines[i]
            is_ctype = re.search(r"header\(\s*['\"]Content-Type:", line)
            # l'en-tete suivante est-elle un attachment ?
            nxt = lines[i + 1] if i + 1 < len(lines) else ''
            if is_ctype and 'Content-Disposition' in nxt and 'attachment' in nxt:
                indent = re.match(r'[ \t]*', line).group(0)
                if CALL not in (out[-1] if out else ''):
                    out.append(indent + "// Empeche toute sortie parasite de corrompre le fichier")
                    out.append(indent + CALL)
                    n += 1
            out.append(line)
            i += 1

        s = chr(10).join(out)

        # helpers.php doit etre charge pour disposer de la fonction
        rel = os.path.relpath('includes/helpers.php', base).replace(chr(92), '/')
        if 'includes/helpers.php' not in s:
            s2, k = re.subn(r"(require_once __DIR__ \. '[^']*vendor/autoload\.php';)",
                            r"\1" + chr(10) + "require_once __DIR__ . '%s';" % rel,
                            s, count=1)
            if k:
                s = s2

        if s != o:
            io.open(p, 'w', encoding='utf-8').write(s)
            patched.append((p, n))
        elif CALL in s:
            already.append(p)

print('Generateurs securises (%d) :' % len(patched))
for p, n in patched:
    print('   %-62s %d point(s) de telechargement' % (p, n))
if already:
    print('Deja traites : %d' % len(already))
