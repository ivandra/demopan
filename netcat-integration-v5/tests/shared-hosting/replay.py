import http.client
from http.cookies import SimpleCookie
import json
import os
from pathlib import Path
import re
import sys
import time
from urllib.parse import urlencode

ROOT = Path(os.environ['NETCAT_QA_OUTPUT']).resolve()
cookies = {}


def request(path, fields=None):
    connection = http.client.HTTPConnection(os.environ.get('NETCAT_REPRO_ADDRESS', '127.0.0.1'), int(os.environ.get('NETCAT_REPRO_PORT', '8087')), timeout=45)
    headers = {'Host': 'dev.lifepodrostka.ru',
               'Cookie': '; '.join(k + '=' + v for k, v in cookies.items())}
    if fields is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
    connection.request('POST' if fields is not None else 'GET', path,
                       urlencode(fields) if fields is not None else None, headers)
    response = connection.getresponse()
    for key, value in response.getheaders():
        if key.lower() == 'set-cookie':
            parsed = SimpleCookie()
            parsed.load(value)
            cookies.update({key: item.value for key, item in parsed.items()})
    body = response.read().decode('utf-8', errors='replace')
    status = response.status
    connection.close()
    return status, body


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else 'install'
    print('Baseline homepage status:', request('/')[0], flush=True)
    status, body = request('/_qa_auth.php')
    print('Local QA admin authorization HTTP:', status, flush=True)
    status, body = request('/zhp-install.php')
    (ROOT / 'installer-dashboard.html').write_text(body)
    print('Installer dashboard HTTP:', status, flush=True)
    match = re.search(r'<script id="zhp-installer-config" type="application/json">(.*?)</script>', body, re.S)
    if status != 200 or not match:
        raise RuntimeError('Installer dashboard unavailable; response saved privately')
    token = json.loads(match[1])['token']
    records = []
    started = time.monotonic()
    try:
        for index in range(5000):
            status, body = request('/zhp-install.php?ajax=1', {'mode': mode, 'token': token})
            try:
                result = json.loads(body)
            except json.JSONDecodeError:
                (ROOT / 'failed-response.txt').write_text(body)
                raise RuntimeError('Non-JSON response, HTTP ' + str(status))
            records.append({'step': index + 1, 'http': status, **result})
            if index % 25 == 0 or not result.get('ok') or result.get('done'):
                print('Step', index + 1, 'HTTP', status, 'ok', result.get('ok'),
                      'done', result.get('done'), 'message', result.get('message'), flush=True)
            if not result.get('ok') or status >= 400:
                raise RuntimeError('Installer stopped')
            if result.get('done'):
                print('Completed', mode, 'in', index + 1, 'requests,',
                      round(time.monotonic() - started, 2), 'seconds', flush=True)
                return
        raise RuntimeError('Step limit exceeded')
    finally:
        (ROOT / ('replay-' + mode + '.json')).write_text(json.dumps(records, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
