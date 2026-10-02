# -*- coding: utf-8 -*-
"""
Supprime toute exposition de chemin serveur dans les messages d'erreur.

Plusieurs generateurs affichaient le chemin disque absolu du gabarit manquant
(« Template introuvable : /var/www/html/pieces/... »), revelant l'arborescence
du serveur. Le detail part desormais dans le journal via erreur_gabarit_absent().
"""
import io, os, re

remplacements = [
    # die("Template introuvable : $templateFile");
    (re.compile(r'''die\(\s*"Template introuvable\s*:\s*\$(templateFile|templatePath)"\s*\)\s*;'''),
     lambda m: 'erreur_gabarit_absent($%s);' % m.group(1)),

    # die("Erreur : Template introuvable ($templatePath). ...");
    (re.compile(r'''die\(\s*"Erreur\s*:\s*Template introuvable \(\$(templateFile|templatePath)\)[^"]*"\s*\)\s*;'''),
     lambda m: 'erreur_gabarit_absent($%s);' % m.group(1)),

    # bloc que j'avais ecrit : vider_tampon_sortie + http_response_code + echo basename
    (re.compile(r'''vider_tampon_sortie\(\);\s*\r?\n\s*http_response_code\(500\);\s*\r?\n\s*'''
                r'''header\('Content-Type: text/plain; charset=utf-8'\);\s*\r?\n\s*'''
                r'''echo "Erreur : gabarit introuvable \(" \. basename\(\$(templateFile|templatePath)\) \. "\)\.";\s*\r?\n\s*exit;'''),
     lambda m: 'erreur_gabarit_absent($%s);' % m.group(1)),
]

touches = []
for base, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in {'vendor', 'PHPMailer', '_archive', '.docker', '.git'}]
    for f in sorted(files):
        if not f.endswith('.php'):
            continue
        p = os.path.join(base, f)
        s = io.open(p, encoding='utf-8').read()
        o = s
        total = 0
        for pat, rep in remplacements:
            s, n = pat.subn(rep, s)
            total += n
        if total:
            # helpers.php fournit erreur_gabarit_absent()
            if 'includes/helpers.php' not in s:
                rel = os.path.relpath('includes/helpers.php', base).replace(chr(92), '/')
                s = re.sub(r"(require_once __DIR__ \. '[^']*vendor/autoload\.php';)",
                           r"\1" + chr(10) + "require_once __DIR__ . '/%s';" % rel,
                           s, count=1)
            io.open(p, 'w', encoding='utf-8').write(s)
            touches.append((p.replace(chr(92), '/'), total))

print('Fichiers assainis (%d) :' % len(touches))
for p, n in touches:
    print('   %-58s %d message(s)' % (p, n))
