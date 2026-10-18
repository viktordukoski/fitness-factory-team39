"""HTTP integration checks against an isolated, seeded local demonstration.
Run: python tests/http_workflows.py http://127.0.0.1:8098
Creates fictional records; never point it at a real member database.
"""
import http.cookiejar
import json
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from html import unescape
sys.stdout.reconfigure(encoding='utf-8')

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8098').rstrip('/')
if not re.match(r'^http://(?:127\.0\.0\.1|localhost)(?::\d+)?$', BASE):
    raise SystemExit('This test only permits a local loopback demonstration URL.')
results = []

class Client:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
    def get(self, page, **params):
        return self.request('/?' + urllib.parse.urlencode(dict(page=page, lang='en', **params)))
    def request(self, path, data=None):
        req = urllib.request.Request(BASE + path, data=urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            with self.opener.open(req, timeout=20) as r:
                return r.status, r.read().decode(), r.url, dict(r.headers)
        except urllib.error.HTTPError as r:
            return r.code, r.read().decode(), r.url, dict(r.headers)
    def post(self, page, action, **fields):
        _, html, _, _ = self.get(page, **({'id': fields['id']} if 'id' in fields else {}))
        token = re.search(r'name="csrf" value="([^"]+)"', html)
        assert token, 'CSRF field missing on ' + page
        return self.request('/?page=' + page + ('&id='+str(fields['id']) if 'id' in fields else ''), dict(csrf=token[1],action=action,**fields))

def check(name, fn):
    try:
        fn()
        results.append(dict(test=name, status='PASS'))
    except Exception as e:
        results.append(dict(test=name, status='NEEDS FIX', reason=str(e)))

def expect(value, message='Assertion failed'):
    assert value, message

visitor = Client()
member = Client()
admin = Client()
suffix = str(time.time_ns())

def public_routes():
    for page in ['home','about','facilities','services','trainers','classes','memberships','news','gallery','contact','faq','search','privacy','cookies','terms','login','register','newsletter']:
        status, body, _, _ = visitor.get(page)
        expect(status == 200, f'{page}: HTTP {status}')
        expect('<main' in body and '<h1' in body, f'{page}: semantic main/heading missing')
        expect('Warning:' not in body and 'Fatal error' not in body, f'{page}: leaked runtime error')
    expect(visitor.get('missing-route')[0] == 404)
    expect(visitor.get('health')[0] == 200)
check('All public routes, health and 404', public_routes)

def register_flow():
    status, body, url, _ = member.post('register','register',name='HTTP Demo Member',email=f'http-{suffix}@example.test',password='HttpFactory!2026',consent='1')
    expect(status == 200 and 'page=dashboard' in url, f'Registration failed: {status} {url}')
    expect('HTTP Demo Member' in body)
    expect(member.get('admin')[0] == 403, 'Member accessed admin')
    expect(visitor.get('dashboard')[2].find('page=login') >= 0)
check('Registration, dashboard and member/admin access boundary', register_flow)

def checkout_flow():
    status, body, _, _ = member.get('checkout',id=4)
    expect(status==200)
    key = re.search(r'name="idempotency_key" value="([^"]+)"',body)[1]
    fields=dict(id=4,plan_id=4,method='card',scenario='success',consent=1,idempotency_key=key)
    first=member.post('checkout','checkout',**fields)
    expect(first[0]==200 and 'page=order' in first[2], f'Checkout failed: {first[0]}')
    again=member.post('checkout','checkout',**fields)
    expect(first[2]==again[2], 'Repeated checkout created another order')
    expect('DEMO' in member.get('passes')[1])
    order_id=urllib.parse.parse_qs(urllib.parse.urlparse(first[2]).query)['id'][0]
    expect(visitor.get('order',id=order_id)[2].find('page=login') >= 0)
    check('Account order isolation after administrator login',lambda: expect(admin.get('order',id=order_id)[0]==404))
check('Administrator login',lambda: expect(admin.post('login','login',email='admin@demo.test',password='FactoryAdmin!2026')[0]==200))
check('Checkout, repeated submission and digital pass', checkout_flow)

def booking_flow():
    _, body, _, _ = member.get('classes',availability='available')
    ids = list(dict.fromkeys(re.findall(r'page=class(?:&amp;|&)lang=en(?:&amp;|&)id=(\d+)',body)))
    expect(ids,'No available class links')
    booked=None
    for session_id in ids:
        status, body, url, _ = member.post('booking','book',id=session_id,session_id=session_id,consent=1)
        if status==200 and 'page=bookings' in url:
            booked=session_id
            break
    expect(booked,'No class accepted the new active demo member')
    expect(member.post('booking','book',id=booked,session_id=booked,consent=1)[0]==409)
    match=re.search(r'name="booking_id" value="(\d+)"',body)
    expect(match,'Cancellation control missing')
    status, body, _, _=member.post('bookings','cancel_booking',id=match[1],booking_id=match[1])
    expect(status==200 and 'cancelled' in body.lower(),'Cancellation failed')
check('Schedule → booking review → confirm → duplicate rejection → cancel', booking_flow)

def favorites_flow():
    expect(member.post('detail','favorite',id=1,content_id=1)[0]==200)
    expect('Strength foundations' in member.get('favorites')[1])
    expect(member.post('favorites','favorite',id=1,content_id=1)[0]==200)
    expect('Strength foundations' not in member.get('favorites')[1])
check('Favorite add/list/remove', favorites_flow)

def contact_newsletter():
    bad=visitor.post('contact','contact',name='x',email='bad',subject='x',message='short')
    expect(bad[0]==422)
    subject='HTTP inquiry '+suffix
    expect(visitor.post('contact','contact',name='HTTP Visitor',email='visitor@example.test',subject=subject,message='Please confirm the precise entrance.',consent=1)[0]==200)
    expect(subject in admin.get('admin',section='inquiries')[1])
    expect(visitor.post('newsletter','subscribe',email=f'newsletter-{suffix}@example.test',consent=1)[0]==200)
    expect(visitor.post('newsletter','subscribe',email=f'newsletter-{suffix}@example.test',consent=1)[0]==409)
    _, body, _, _=visitor.get('newsletter')
    token=re.search(r'name="token" value="([^"]+)"',body)
    expect(token,'Unsubscribe token/form unavailable')
    expect(visitor.post('newsletter','unsubscribe',token=token[1])[0]==200)
check('Inquiry validation/storage/admin visibility and newsletter lifecycle',contact_newsletter)

def csrf_headers():
    status,body,_,headers=member.request('/?page=settings',dict(action='profile',name='Attack',language='en',csrf='invalid'))
    expect(status==419)
    expect('Content-Security-Policy' in headers)
    expect('noindex' in headers.get('X-Robots-Tag',''))
check('CSRF rejection, security headers and noindex',csrf_headers)

def language_routes():
    for code in ['mk','en','sq','de','fr','it']:
        status,body,_,_=visitor.request('/?page=services&lang='+code)
        expect(status==200 and f'<html lang="{code}">' in body)
    expect('Krafttraining verstehen' in visitor.request('/?page=services&lang=de')[1])
    expect('Основи на силов тренинг' in visitor.request('/?page=services&lang=mk')[1])
check('All six language routes and localized database content',language_routes)

def admin_routes():
    for section in ['dashboard','content','sessions','rooms','plans','users','bookings','orders','inquiries','subscribers','settings','sources','audit']:
        expect(admin.get('admin',section=section)[0]==200,section)
    for section in ['content','sessions','rooms','plans','sources','settings']:
        expect(admin.get('admin',section=section,edit='new')[0]==200,section+' editor')
check('All protected administration pages and editors',admin_routes)

def logout_flow():
    expect(member.post('settings','logout')[0]==200)
    expect('page=login' in member.get('dashboard')[2])
    expect(member.post('login','login',email=f'http-{suffix}@example.test',password='HttpFactory!2026')[0]==200)
check('Logout and sign-in again',logout_flow)

print(json.dumps(dict(checks=results,passed=sum(r['status']=='PASS' for r in results),total=len(results)),ensure_ascii=False,indent=2))
sys.exit(any(r['status']!='PASS' for r in results))
