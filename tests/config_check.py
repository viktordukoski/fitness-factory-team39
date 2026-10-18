"""Static checks; this does not replace Docker build or RepoRun Validate."""
from pathlib import Path
import json
import re
import yaml

root=Path(__file__).resolve().parents[1]
compose=yaml.safe_load((root/'docker-compose.yml').read_text())
stack=yaml.safe_load((root/'stack.yml').read_text())
results=[]
def check(name, condition):
    results.append({'test':name,'status':'PASS' if condition else 'NEEDS FIX'})
for name,service in compose['services'].items():
    check(name+' explicit CPU/RAM limits',bool(service.get('cpus')) and bool(service.get('mem_limit')))
    check(name+' no prohibited Compose fields',not any(k in service for k in ['ports','env_file','labels','restart','networks','privileged']))
    mounts=service.get('volumes',[])
    check(name+' relative .stack-data persistence',all(isinstance(v,str) and v.startswith('./.stack-data/') and 'docker.sock' not in v for v in mounts))
check('No top-level custom networks','networks' not in compose)
check('Single application ingress',stack=={'version':1,'ingress':[{'name':'app','service':'web','port':80,'primary':True,'access':{'type':'public'}}]})
check('No database ingress',all(i['service']!='db' for i in stack['ingress']))
cpu_limits=[service.get('cpus') for service in compose['services'].values()]
memory_limits=[service.get('mem_limit') for service in compose['services'].values()]
literal_cpu=all(isinstance(value,(int,float)) and not isinstance(value,bool) and value>0 for value in cpu_limits)
literal_memory=all(isinstance(value,str) and re.fullmatch(r'[1-9][0-9]*[kmg]',value.lower()) for value in memory_limits)
check('Hosted resource limits are literal for RepoRun validation',literal_cpu and literal_memory)
memory_bytes=sum(int(value[:-1])*{'k':1024,'m':1024**2,'g':1024**3}[value[-1].lower()] for value in memory_limits) if literal_memory else None
check('Hosted limits fit observed 0.5 CPU and 1000 MB quota',literal_cpu and sum(cpu_limits)<=0.5 and memory_bytes is not None and memory_bytes<=1_000_000_000)
names=re.findall(r'\$\{([A-Z_][A-Z0-9_]*)(?::-[^}]*)?\}',(root/'docker-compose.yml').read_text())
example={line.split('=',1)[0] for line in (root/'.env.example').read_text().splitlines() if line and not line.startswith('#')}
required=set(re.findall(r'\$\{([A-Z_][A-Z0-9_]*)\}',(root/'docker-compose.yml').read_text()))
check('Required configuration documented',required<=example)
check('Environment names conform',all(re.fullmatch(r'[A-Z_][A-Z0-9_]{0,127}',n) for n in names))
check('Local host port is loopback only',yaml.safe_load((root/'compose.local.yml').read_text())['services']['web']['ports'][0].startswith('127.0.0.1:'))
check('No committed real environment file',not (root/'.env').exists())
check('Persistent runtime excluded from Git and Docker context',all(any(line.rstrip('/')=='.stack-data' for line in (root/f).read_text().splitlines()) for f in ['.gitignore','.dockerignore']))
print(json.dumps({'checks':results,'passed':sum(r['status']=='PASS' for r in results),'total':len(results)},indent=2))
raise SystemExit(any(r['status']!='PASS' for r in results))
