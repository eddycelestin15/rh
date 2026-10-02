# -*- coding: utf-8 -*-
"""
Parmi les requetes SQL a variables, isole celles ou la variable interpolee
provient reellement d'une entree utilisateur ($_GET / $_POST / $_REQUEST /
$_COOKIE) sans passage par une liste blanche ou un cast.

C'est la difference entre :
  - interpolation structurelle  : nom de table choisi dans un tableau interne  (sur)
  - injection                   : "... WHERE im = $_GET['im']"                 (a corriger)
"""
import io, os, re

SQL_KW = re.compile(r'\b(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|WHERE|VALUES|SET|JOIN|ORDER\s+BY|GROUP\s+BY|LIMIT|HAVING)\b', re.I)
VAR = re.compile(r'\$([a-zA-Z_][a-zA-Z0-9_]*)|\{\$([a-zA-Z_][a-zA-Z0-9_]*)')
DQ = re.compile(r'"((?:[^"\\]|\\.)*)"', re.S)

SUPER = r'\$_(GET|POST|REQUEST|COOKIE)'
# Assignation directe depuis une superglobale, sans cast ni liste blanche
TAINT_ASSIGN = re.compile(
    r'\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([^;]*' + SUPER + r'[^;]*);')
# Marqueurs qui rendent la valeur sure
SAFE = re.compile(r'\(int\)|\(float\)|intval|floatval|in_array|array_key_exists|'
                  r'real_escape_string|quote\(|'
                  r'\[\s*\$[a-zA-Z_]\w*\s*\]\s*\?\?|preg_replace|ctype_digit|'
                  r'number_format|round|implode\(.*array_fill|str_repeat', re.I)

rows = []
for base, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in {'vendor', 'PHPMailer', '_archive', '.docker', '.git', 'assets'}]
    for f in sorted(files):
        if not f.endswith('.php'):
            continue
        p = os.path.join(base, f).replace(chr(92), '/').lstrip('./')
        src = io.open(p, encoding='utf-8', errors='replace').read()

        # variables « teintees » du fichier
        tainted = {}
        for m in TAINT_ASSIGN.finditer(src):
            name, expr = m.group(1), m.group(2)
            if not SAFE.search(expr):
                tainted[name] = expr.strip()[:80]

        if not tainted:
            continue

        for m in DQ.finditer(src):
            body = m.group(1)
            if len(body) < 15 or not SQL_KW.search(body):
                continue
            used = {g1 or g2 for g1, g2 in VAR.findall(body)}
            hit = used & set(tainted)
            if hit:
                line = src[:m.start()].count(chr(10)) + 1
                rows.append((p, line, sorted(hit), body.strip()[:90].replace(chr(10), ' ')))

        # superglobale directement dans la chaine SQL
        for m in DQ.finditer(src):
            body = m.group(1)
            if SQL_KW.search(body) and re.search(SUPER, body):
                line = src[:m.start()].count(chr(10)) + 1
                rows.append((p, line, ['$_GET/$_POST direct'], body.strip()[:90]))

seen, uniq = set(), []
for r in rows:
    if (r[0], r[1]) in seen:
        continue
    seen.add((r[0], r[1]))
    uniq.append(r)

uniq.sort()
print('INJECTIONS SQL PROBABLES : %d' % len(uniq))
print('=' * 78)
cur = None
for p, line, vs, snip in uniq:
    if p != cur:
        print(chr(10) + p)
        cur = p
    print('   L%-5d  %s' % (line, ', '.join('$' + v if not v.startswith('$') else v for v in vs)))
    print('           %s' % snip)
