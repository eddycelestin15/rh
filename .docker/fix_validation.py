# -*- coding: utf-8 -*-
"""
Ajoute un controle des champs obligatoires aux endpoints qui lisaient
$_POST / $_GET sans verification.

Sans ce controle, PHP 8 emet un avertissement par champ absent et le script
poursuit : insertion de lignes vides, ecrasement de donnees par NULL.
"""
import io, os, re

# fichier -> (superglobale, champs obligatoires)
REQUIS = {
 'api/allocation/api_allocation.php':
   ('$_POST', ['im_parent', 'nom', 'prenoms', 'date_naiss', 'lieu_naiss',
               'sexe', 'num_copie', 'type_filiation', 'inscrit_sur_bc']),
 'actions/personnel/update_affectation.php':
   ('$_POST', ['type_acte', 'num_acte', 'date_acte', 'fonction',
               'lieu_affectation', 'localite']),
 'actions/personnel/save_step_affectation.php':
   ('$_POST', ['type_acte', 'num_acte', 'date_acte', 'fonction',
               'lieu_affectation', 'localite']),
 'api/messagerie/send_message.php':
   ('$_POST', ['destinataire', 'objet', 'message']),
 'api/carriere/get_indice_by_libelle.php':
   ('$_GET',  ['corps_id', 'libelle_grade']),
 'api/allocation/get_region_dcf.php':
   ('$_GET',  ['im']),
 'actions/compte/save_responsable.php':
   ('$_POST', ['nom', 'prenoms', 'niveau', 'role_specifique']),
}

GUARD = """
// Champs obligatoires : sans ce controle, les champs absents partaient en base
// sous forme de valeurs nulles (et PHP 8 emettait un avertissement par champ).
require_once __DIR__ . '/%s';
exiger_champs(%s, [%s]);
"""

done = []
for path, (source, champs) in REQUIS.items():
    if not os.path.exists(path):
        print('  ABSENT %s' % path); continue
    s = io.open(path, encoding='utf-8').read()
    if 'exiger_champs(' in s:
        print('  deja fait %s' % path); continue

    rel = os.path.relpath('includes/helpers.php', os.path.dirname(path)).replace(chr(92), '/')
    liste = ', '.join("'%s'" % c for c in champs)
    guard = GUARD % (rel, source, liste)

    # inserer juste apres le require de check_session.php
    s2, n = re.subn(r"(require_once __DIR__ \. '[^']*check_session\.php';[ \t]*\r?\n)",
                    lambda m: m.group(1) + guard.lstrip(chr(10)), s, count=1)
    if n == 0:
        print('  !! point d insertion introuvable dans %s' % path); continue

    io.open(path, 'w', encoding='utf-8').write(s2)
    done.append((path, len(champs)))

print('Endpoints securises (%d) :' % len(done))
for p, k in done:
    print('   %-48s %d champs obligatoires' % (p, k))
