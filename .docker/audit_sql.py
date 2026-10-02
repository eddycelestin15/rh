# -*- coding: utf-8 -*-
"""
Repere les requetes SQL construites par interpolation ou concatenation
de variables PHP (risque d'injection).

Analyse les chaines litterales qui ressemblent a du SQL et signale celles
qui contiennent une variable, en distinguant :
  - INTERPOLEE  : "... WHERE im = $im"        (double quotes)
  - CONCATENEE  : '... WHERE im = ' . $im
"""
import io, os, re

SQL_KW = re.compile(r'\b(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|WHERE|VALUES|SET|JOIN|ORDER\s+BY|GROUP\s+BY|LIMIT|HAVING)\b', re.I)
VAR = re.compile(r'\$[a-zA-Z_][a-zA-Z0-9_]*|\{\$[^}]+\}')

# Chaine PHP double-quote ou simple-quote, sur une ou plusieurs lignes
DQ = re.compile(r'"((?:[^"\\]|\\.)*)"', re.S)
SQ = re.compile(r"'((?:[^'\\]|\\.)*)'", re.S)

findings = []

for base, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in {'vendor', 'PHPMailer', '_archive', '.docker', '.git', 'assets'}]
    for f in sorted(files):
        if not f.endswith('.php'):
            continue
        p = os.path.join(base, f).replace(chr(92), '/').lstrip('./')
        src = io.open(p, encoding='utf-8', errors='replace').read()

        # 1) Chaines double-quote contenant du SQL ET une variable -> interpolation
        for m in DQ.finditer(src):
            body = m.group(1)
            if len(body) < 15 or not SQL_KW.search(body):
                continue
            vars_found = VAR.findall(body)
            if not vars_found:
                continue
            line = src[:m.start()].count(chr(10)) + 1
            findings.append((p, line, 'INTERPOLEE', sorted(set(vars_found))))

        # 2) Concatenation : chaine SQL suivie de  . $var
        for m in re.finditer(r'''(["'])((?:[^"'\\]|\\.)*?)\1\s*\.\s*(\$[a-zA-Z_][a-zA-Z0-9_]*(?:\[[^\]]*\])?)''', src):
            body = m.group(2)
            if len(body) < 10 or not SQL_KW.search(body):
                continue
            line = src[:m.start()].count(chr(10)) + 1
            findings.append((p, line, 'CONCATENEE', [m.group(3)]))

# Dedoublonner par (fichier, ligne)
seen, uniq = set(), []
for fnd in findings:
    key = (fnd[0], fnd[1])
    if key in seen:
        continue
    seen.add(key)
    uniq.append(fnd)

uniq.sort()
by_file = {}
for p, line, kind, vs in uniq:
    by_file.setdefault(p, []).append((line, kind, vs))

print('Requetes SQL a variables : %d, dans %d fichiers' % (len(uniq), len(by_file)))
print('=' * 78)
for p in sorted(by_file):
    print('%s  (%d)' % (p, len(by_file[p])))
    for line, kind, vs in by_file[p]:
        print('   L%-5d %-11s %s' % (line, kind, ', '.join(vs)[:70]))
