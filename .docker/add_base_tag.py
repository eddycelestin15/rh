# -*- coding: utf-8 -*-
"""
Injecte <base href="..."> en tete de <head> dans chaque page complete.

Toutes les URL relatives du document (assets, liens, formulaires, fetch)
se resolvent alors depuis la racine de l'application, quel que soit le
sous-dossier ou vit le script. C'est ce qui permet aux 461 URL du projet
de rester ecrites en « api/agents/get_data.php » sans se soucier de la
profondeur du fichier courant.
"""
import io, os, re

SKIP = {'includes/bootstrap.php'}
BASE_LINE = '    <?php echo base_tag(); ?>'

targets = []
for base, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in {'vendor', 'PHPMailer', '_archive', '.docker', '.git'}]
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.normpath(os.path.join(base, f)).replace(chr(92), '/').lstrip('./')
        if p in SKIP:
            continue
        s = io.open(p, encoding='utf-8').read()
        if '<head>' in s:
            targets.append((p, s))

done, already = 0, 0
for p, s in targets:
    if 'base_tag()' in s or '<base ' in s:
        already += 1
        continue

    rel = os.path.relpath('includes/bootstrap.php', os.path.dirname(p) or '.').replace(chr(92), '/')
    boot = "<?php require_once __DIR__ . '/%s'; ?>" % rel

    # Le fichier commence-t-il deja par du PHP ? Sinon on prefixe l'amorcage.
    if not s.lstrip().startswith('<?php'):
        s = boot + chr(10) + s
    else:
        # bootstrap est deja tire par config.php dans ces pages, mais on le rend
        # explicite pour que base_tag() existe meme si la page evolue.
        if 'bootstrap.php' not in s:
            s = re.sub(r'^(<\?php[ \t]*\r?\n?)', r'\1' + boot.replace('<?php ', '').replace(' ?>', '') + chr(10),
                       s, count=1)

    s = s.replace('<head>', '<head>' + chr(10) + BASE_LINE, 1)
    io.open(p, 'w', encoding='utf-8').write(s)
    done += 1

print('pages traitees      : %d' % done)
print('deja pourvues       : %d' % already)
print('total pages <head>  : %d' % len(targets))
