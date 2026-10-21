"""Independent, synthetic server-side SRP vectors; no Apple credentials.

Run with Python 3; writes fixtures/apple-srp.json. The group is fetched directly
from RFC 5054 Appendix A, not from the implementation under test. Server S uses
(A * v**u)**b rather than the client's subtraction/exponent formula.
Apple GSA wire conventions: https://github.com/foxt/js-srp-gsa (protocol evidence
only). This generator is independently written using Python stdlib primitives.
"""

import base64
import hashlib
import json
from pathlib import Path
import re
import urllib.request


def h(value):
    return hashlib.sha256(value).digest()


def raw(value):
    return value.to_bytes(max(1, (value.bit_length() + 7) // 8), 'big')


def b64(value):
    return base64.b64encode(value).decode('ascii')


rfc_url = 'https://www.rfc-editor.org/rfc/rfc5054.txt'
text = urllib.request.urlopen(rfc_url).read().decode('ascii')
section = text.split('2048-bit Group', 1)[1].split('The generator is:', 1)[0]
prime_hex = ''.join(re.findall(r'\b[0-9A-F]{8}\b', section))
n = int(prime_hex, 16)
assert n.bit_length() == 2048 and len(prime_hex) == 512
pad = lambda value: value.to_bytes(256, 'big')
k = int.from_bytes(h(raw(n) + pad(2)), 'big')

# Deterministically search realistic 256-bit a until A needs leading-zero pad.
a = int.from_bytes(h(b'SymconiCloud independent synthetic ephemeral'), 'big')
while pow(2, a, n).bit_length() > 2040:
    a += 1
A = pow(2, a, n)
assert len(raw(A)) < 256
b = int.from_bytes(h(b'SymconiCloud synthetic server ephemeral'), 'big')
username = 'synthetic.fixture@example.invalid'
password = 'Synthetic-Passwort-\u00e4-2026!'
salt = bytes.fromhex('00112233445566778899aabbccddeeff')
iterations = 1000
vectors = []
for protocol in ['s2k', 's2k_fo']:
    password_input = h(password.encode('utf-8'))
    if protocol == 's2k_fo':
        password_input = password_input.hex().encode('ascii')
    derived = hashlib.pbkdf2_hmac('sha256', password_input, salt, iterations, 32)
    x = int.from_bytes(h(salt + h(b':' + derived)), 'big')
    v = pow(2, x, n)
    B = (k * v + pow(2, b, n)) % n
    u = int.from_bytes(h(pad(A) + pad(B)), 'big')
    assert B != 0 and u != 0
    server_secret = pow((A * pow(v, u, n)) % n, b, n)
    K = h(raw(server_secret))
    group_hash = bytes(left ^ right for left, right in zip(h(raw(n)), h(pad(2))))
    m1 = h(group_hash + h(username.encode()) + salt + raw(A) + raw(B) + K)
    m2 = h(raw(A) + m1 + K)
    vectors.append({
        'protocol': protocol,
        'username': username,
        'password': password,
        'privateHex': raw(a).hex(),
        'serverPrivateHex': raw(b).hex(),
        'challenge': {'protocol': protocol, 'iteration': iterations,
                      'salt': b64(salt), 'b': b64(raw(B)), 'c': 'synthetic-continuation'},
        'expected': {'a': b64(raw(A)), 'm1': b64(m1), 'm2': b64(m2),
                     'serverKeyHex': K.hex(), 'aByteLength': len(raw(A))},
    })

target = Path(__file__).parent / 'fixtures' / 'apple-srp.json'
target.parent.mkdir(parents=True, exist_ok=True)
target.write_text(json.dumps({
    'provenance': {'group': rfc_url, 'groupSection': 'Appendix A, 2048-bit Group',
                   'method': 'Independent server verifier with Python hashlib and builtin pow; all inputs synthetic',
                   'generator': 'tests/generate-srp-fixtures.py'},
    'group': {'nHex': prime_hex, 'g': 2}, 'vectors': vectors,
}, indent=4, ensure_ascii=False) + '\n', encoding='utf-8')
print(f'Wrote {len(vectors)} server-side vectors; A is {len(raw(A))} bytes: {target}')
