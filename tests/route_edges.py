"""Read-only HTTP regression checks for repaired validation, paging and metadata."""
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from html import unescape

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8098').rstrip('/')
if not re.fullmatch(r'http://(?:127\.0\.0\.1|localhost)(?::\d+)?', BASE):
    raise SystemExit('Use an isolated local demonstration URL.')

def get(**params):
    url = BASE + '/?' + urllib.parse.urlencode(params)
    try:
        with urllib.request.urlopen(url, timeout=15) as response:
            return response.status, response.read().decode('utf-8')
    except urllib.error.HTTPError as response:
        return response.code, response.read().decode('utf-8')

results = []
def check(name, test):
    try:
        assert test()
        results.append({'test': name, 'status': 'PASS'})
    except Exception as error:
        results.append({'test': name, 'status': 'NEEDS FIX', 'reason': str(error)})

check('Malformed calendar date returns HTTP 400', lambda: get(page='classes', date='not-a-date')[0] == 400)
check('Impossible calendar date returns HTTP 400', lambda: get(page='classes', date='2026-02-30')[0] == 400)
check('Oversized search returns HTTP 400', lambda: get(page='search', q='x' * 101)[0] == 400)
check('Unknown verification token returns HTTP 404', lambda: get(page='verify', token='not-a-real-pass')[0] == 404)

def news_pages():
    first_status, first = get(page='news', lang='en', p=1)
    next_status, second = get(page='news', lang='en', p=2)
    return first_status == next_status == 200 and 'class="pagination"' in first and first != second and 'aria-current="page"' in second
check('Seeded news has a working second page', news_pages)

def metadata():
    _, home = get(page='home', lang='en')
    _, about = get(page='about', lang='en')
    titles = [re.search(r'<title>(.*?)</title>', body, re.S).group(1) for body in (home, about)]
    descriptions = [re.search(r'<meta name="description" content="([^"]+)"', body).group(1) for body in (home, about)]
    canonical = re.search(r'<link rel="canonical" href="([^"]+)"', about)
    return titles[0] != titles[1] and descriptions[0] != descriptions[1] and canonical and 'page=about' in unescape(canonical.group(1)) and all('hreflang="' + code + '"' in about for code in ('en','mk','sq','de','fr','it'))
check('Unique metadata, canonical and six language alternates', metadata)
print(json.dumps({'checks': results, 'passed': sum(r['status'] == 'PASS' for r in results), 'total': len(results)}, indent=2))
raise SystemExit(any(r['status'] != 'PASS' for r in results))
