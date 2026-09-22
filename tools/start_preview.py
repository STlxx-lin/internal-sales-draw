"""Start an isolated, persistent local demo. Requires Windows PHP and MySQL binaries."""
import argparse, json, shutil, subprocess, tempfile, socket, time
from pathlib import Path

def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0)); return s.getsockname()[1]

def wait(p, child):
    for _ in range(100):
        if child.poll() is not None: raise RuntimeError('Preview process failed to start')
        try:
            with socket.create_connection(('127.0.0.1',p), .2): return
        except OSError: time.sleep(.1)
    raise RuntimeError('Preview startup timed out')

parser=argparse.ArgumentParser()
parser.add_argument('--php', required=True);parser.add_argument('--mysqld', required=True)
a=parser.parse_args()
root=Path(__file__).resolve().parents[1]
work=Path(tempfile.mkdtemp(prefix='lottery-preview-'))
site=work/'site';site.mkdir();data=work/'data';data.mkdir();(site/'uploads').mkdir()
for name in ['admin.php','admin_records.php','expense.php','expense_service.php','expense_ajax.php','index.php','history.php','lottery.php','register.php']:
    shutil.copy2(root/name,site/name)
for name in ['assets','font-awesome-4.7.0','views']: shutil.copytree(root/name,site/name)
mp,wp=port(),port()
base=[a.mysqld,'--no-defaults','--basedir='+str(Path(a.mysqld).resolve().parent.parent),'--datadir='+str(data)]
subprocess.run(base+['--initialize-insecure'],check=True,capture_output=True,timeout=60)
flags=getattr(subprocess,'CREATE_NO_WINDOW',0)
mlog=open(work/'mysql.log','wb')
mysql=subprocess.Popen(base+[f'--port={mp}','--bind-address=127.0.0.1','--innodb-buffer-pool-size=32M'],stdin=subprocess.DEVNULL,stdout=mlog,stderr=mlog,creationflags=flags)
wait(mp,mysql)
php=[a.php,'-n','-d','extension_dir='+str(Path(a.php).resolve().parent/'ext'),'-d','extension=php_pdo_mysql.dll','-d','extension=php_fileinfo.dll','-d','date.timezone=Asia/Shanghai','-d','upload_max_filesize=10M','-d','post_max_size=12M','-d','session.save_path='+str(work)]
config=f"""<?php
$pdo = new PDO('mysql:host=127.0.0.1;port={mp};dbname=preview;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
define('SITE_NAME','销售部活动中心'); define('PREVIEW_MODE',true); define('UPLOAD_PATH','uploads/');
function getUserIP() {{ return '127.0.0.1'; }}
function checkUser($pdo,$ip) {{ return $pdo->query('SELECT * FROM users WHERE id=1')->fetch(); }}
function logUserAction($action,$status,$detail) {{}}
"""
(site/'config.php').write_text(config,encoding='utf-8')
s=(root/'check_db.php').read_text(encoding='utf-8');schema=s[s.index('$required_tables = ['):s.index('\n];')+3]
seed=f"<?php $pdo=new PDO('mysql:host=127.0.0.1;port={mp};charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $pdo->exec('CREATE DATABASE preview CHARACTER SET utf8mb4');$pdo->exec('USE preview');\n"+schema+"\nforeach($required_tables as $t) $pdo->exec($t['sql']);\n"
seed+='''
$pdo->exec("INSERT INTO projects(id,name) VALUES (1,'九月销售冲刺 · 团队激励'),(2,'季度优秀伙伴表彰')");
$names=['林晓','陈一鸣','王悦','赵子涵','刘洋','周雨桐','许嘉宁','宋晨'];
$s=$pdo->prepare('INSERT INTO users(id,name,ip_address) VALUES(?,?,?)');
foreach($names as $i=>$name) $s->execute([$i+1,$name,'192.168.1.'.($i+10)]);
$pdo->exec("INSERT INTO prizes(id,project_id,name,total_quantity,remaining_quantity,probability) VALUES(1,1,'品质生活购物卡',100,80,40),(2,1,'团队午餐基金',100,80,40),(3,2,'季度优秀伙伴奖励',100,80,100)");
$pdo->exec('INSERT INTO user_project_times(user_id,project_id,total_times,remaining_times,is_visible) SELECT id,1,10,5,1 FROM users');
$s=$pdo->prepare('INSERT INTO lottery_records(user_id,project_id,prize_id,ip_address,created_at) VALUES(?,?,?,?,DATE_SUB(NOW(),INTERVAL ? MINUTE))');
for($i=0;$i<16;$i++) { $prize=($i%3)+1; $s->execute([($i%8)+1,$prize===3?2:1,$i===7?null:$prize,'192.168.1.'.(($i%8)+10),$i*23]); }
$pdo->exec("INSERT INTO expense_records(project_id,user_id,expense_name,amount,reason,status,applicant,is_used,approver,approved_at) VALUES(1,4,'团队奖励报销',200,'由抽奖记录 #4 快速报销生成','approved','赵子涵',1,'管理员',NOW())");
'''
(work/'seed.php').write_text(seed,encoding='utf-8')
subprocess.run(php+[str(work/'seed.php')],check=True,capture_output=True)
plog=open(work/'php.log','wb')
web=subprocess.Popen(php+['-S',f'127.0.0.1:{wp}','-t',str(site)],stdin=subprocess.DEVNULL,stdout=plog,stderr=plog,creationflags=flags)
wait(wp,web)
state={'url':f'http://127.0.0.1:{wp}/admin.php#records','directory':str(work),'mysql_pid':mysql.pid,'php_pid':web.pid,'mysql_port':mp}
(work/'preview.json').write_text(json.dumps(state,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(state,ensure_ascii=False))
