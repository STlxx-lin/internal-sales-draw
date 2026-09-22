"""Run against disposable MySQL and PHP processes; never uses config.php credentials.

python tests/expense_integration.py --php /path/to/php --mysqld /path/to/mysqld
Requires PDO MySQL and fileinfo in the PHP installation's ext directory.
"""
import argparse
import concurrent.futures
import http.cookiejar
import html as html_module
import json
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


def wait_port(port, process):
    deadline = time.monotonic() + 40
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError('Test server exited unexpectedly')
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.2):
                return
        except OSError:
            time.sleep(.1)
    raise RuntimeError('Test server startup timeout')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', required=True)
    parser.add_argument('--mysqld', required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    temp_parent = Path(tempfile.gettempdir()).resolve()
    work = Path(tempfile.mkdtemp(prefix='expense-test-', dir=temp_parent)).resolve()
    processes, logs = [], []
    try:
        site = work / 'site'
        site.mkdir()
        shutil.copytree(root / 'views', site / 'views')
        (site / 'uploads').mkdir()
        data = work / 'data'
        data.mkdir()
        for name in ['admin.php', 'admin_records.php', 'expense.php', 'expense_service.php', 'expense_ajax.php', 'index.php', 'history.php']:
            shutil.copy2(root / name, site / name)
        mysql_port, web_port, web_port2 = free_port(), free_port(), free_port()
        mysql_base = Path(args.mysqld).resolve().parent.parent
        base = [args.mysqld, '--no-defaults', '--basedir=' + str(mysql_base), '--datadir=' + str(data)]
        subprocess.run(base + ['--initialize-insecure'], check=True, capture_output=True, timeout=60)
        log = open(work / 'mysql.log', 'wb')
        logs.append(log)
        mysql = subprocess.Popen(base + [f'--port={mysql_port}', '--bind-address=127.0.0.1', '--innodb-buffer-pool-size=32M'], stdout=log, stderr=log)
        processes.append(mysql)
        wait_port(mysql_port, mysql)
        ext = Path(args.php).resolve().parent / 'ext'
        php = [args.php, '-n', '-d', 'extension_dir=' + str(ext), '-d', 'extension=php_pdo_mysql.dll', '-d', 'extension=php_fileinfo.dll', '-d', 'date.timezone=Asia/Shanghai', '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M', '-d', 'session.save_path=' + str(work)]
        config = f'''<?php
$pdo = new PDO('mysql:host=127.0.0.1;port={mysql_port};dbname=expense_test;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
define('SITE_NAME', '测试');
function getUserIP() {{ return '127.0.0.1'; }}
function checkUser($pdo, $ip) {{ return $pdo->query('SELECT * FROM users LIMIT 1')->fetch(); }}
function logUserAction($action, $status, $detail) {{}}
'''
        (site / 'config.php').write_text(config, encoding='utf-8')
        schema_source = (root / 'check_db.php').read_text(encoding='utf-8')
        schema = schema_source[schema_source.index('$required_tables = ['):schema_source.index('\n];') + 3]
        setup = f"<?php\n$pdo = new PDO('mysql:host=127.0.0.1;port={mysql_port}', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);\n$pdo->exec('CREATE DATABASE expense_test CHARACTER SET utf8mb4');\n$pdo->exec('USE expense_test');\n" + schema + "\nforeach ($required_tables as $table) { $pdo->exec($table['sql']); }\n"
        (work / 'setup.php').write_text(setup, encoding='utf-8')
        subprocess.run(php + [str(work / 'setup.php')], check=True, capture_output=True)
        # A CLI-only query bridge in the temp directory, outside the HTTP document root.
        (work / 'query.php').write_text("<?php require __DIR__ . '/site/config.php'; $s=$pdo->prepare(stream_get_contents(STDIN)); $s->execute(); echo json_encode($s->columnCount() ? $s->fetchAll() : []);", encoding='utf-8')

        def sql(query):
            result = subprocess.run(php + [str(work / 'query.php')], input=query.encode(), capture_output=True, check=True)
            return json.loads(result.stdout)

        sql("INSERT INTO projects (id,name) VALUES (1,'项目一'),(2,'项目二')")
        sql("INSERT INTO users (id,name,ip_address) VALUES (1,'测试用户','127.0.0.1')")
        sql("INSERT INTO prizes (id,project_id,name,total_quantity,remaining_quantity,probability) VALUES (1,1,'测试奖品',10,10,100)")
        sql("INSERT INTO lottery_records (id,user_id,project_id,prize_id,ip_address) VALUES (1,1,1,1,'127.0.0.1'),(10,1,1,1,'127.0.0.1'),(2,1,1,1,'127.0.0.1')")
        for port in [web_port, web_port2]:
            log = open(work / f'php-{port}.log', 'wb')
            logs.append(log)
            process = subprocess.Popen(php + ['-S', f'127.0.0.1:{port}', '-t', str(site)], stdout=log, stderr=log)
            processes.append(process)
            wait_port(port, process)

        def client(port):
            opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

            def request(path, fields=None, upload=None):
                headers = {}
                body = None
                if upload:
                    boundary = 'ExpenseTestBoundary'
                    parts = []
                    for name, value in fields.items():
                        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
                    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="attachment"; filename="receipt.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode() + upload + f'\r\n--{boundary}--\r\n'.encode())
                    body = b''.join(parts)
                    headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
                elif fields is not None:
                    body = urllib.parse.urlencode(fields).encode()
                req = urllib.request.Request(f'http://127.0.0.1:{port}/{path}', data=body, headers=headers)
                try:
                    response = opener.open(req, timeout=15)
                except urllib.error.HTTPError as error:
                    response = error
                return response.read().decode('utf-8')

            page = request('expense.php', {'password': 'admin123'})
            token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', page).group(1)
            return request, token

        request, token = client(web_port)
        request2, token2 = client(web_port2)
        fields = dict(action='add', csrf_token=token, project_id=1, expense_name='测试报销', amount='12.34', applicant='测试人', reason="引号 ' 与 <script> 测试", status='pending')
        request('expense.php', fields, b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF')
        record = sql('SELECT * FROM expense_records')[0]
        expense_id = record['id']
        assert record['amount'] == '12.34' and record['attachment']
        assert (site / record['attachment']).is_file()
        assert len(sql('SELECT * FROM expense_edit_history')) == 1
        # Invalid CSRF and amounts cannot create records.
        for change in [{'csrf_token': 'bad'}, {'amount': '1.999'}, {'amount': 'NaN'}, {'status': 'invalid'}]:
            request('expense.php', dict(fields, **change))
        assert len(sql('SELECT * FROM expense_records')) == 1
        request('expense.php', dict(fields, expense_name='bad upload'), b'<?php echo "not a receipt"; ?>')
        assert len(sql('SELECT * FROM expense_records')) == 1
        # Reject, modify after the existing two-day window, and resubmit.
        approve = dict(action='approve', csrf_token=token, id=expense_id, status='rejected', approver='审批人')
        request('expense.php', approve)
        edit = dict(action='edit', csrf_token=token, id=expense_id, amount='20.00', reason='修改后', edit_reason='更正金额')
        request('expense.php', edit)
        assert sql(f'SELECT status FROM expense_records WHERE id={expense_id}')[0]['status'] == 'rejected'
        sql(f'UPDATE expense_records SET applied_at=DATE_SUB(NOW(), INTERVAL 3 DAY) WHERE id={expense_id}')
        request('expense.php', edit)
        updated = sql(f'SELECT * FROM expense_records WHERE id={expense_id}')[0]
        assert updated['status'] == 'pending' and updated['approver'] is None and updated['approved_at'] is None
        assert updated['amount'] == '20.00' and updated['attachment'] == record['attachment']
        request('expense.php', dict(approve, status='approved'))
        count_history = len(sql('SELECT * FROM expense_edit_history'))
        request('expense.php', approve)
        request('expense.php', dict(action='delete', csrf_token=token, id=expense_id))
        assert sql(f'SELECT status FROM expense_records WHERE id={expense_id}')[0]['status'] == 'approved'
        assert len(sql('SELECT * FROM expense_edit_history')) == count_history
        # Exact marker matching and authoritative project: #10 must not block #1.
        quick = dict(csrf_token=token, record_id=10, project_id=2, amount='0', applied_at='2026-09-22T10:00')
        assert json.loads(request('admin.php?action=quick_expense', quick))['success']
        assert json.loads(request('admin.php?action=quick_expense', dict(quick, record_id=1)))['success']
        assert not json.loads(request('admin.php?action=quick_expense', dict(quick, record_id=1)))['success']
        assert all(int(r['project_id']) == 1 for r in sql('SELECT project_id FROM expense_records WHERE is_used=1'))
        # Hold the target row briefly so both independent HTTP sessions queue for it.
        (work / 'block.php').write_text("<?php require __DIR__ . '/site/config.php'; $pdo->beginTransaction(); $pdo->query('SELECT id FROM lottery_records WHERE id=2 FOR UPDATE'); echo 'locked'; flush(); usleep(800000); $pdo->commit();", encoding='utf-8')
        blocker = subprocess.Popen(php + [str(work / 'block.php')], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        processes.append(blocker)
        assert blocker.stdout.read(6) == b'locked'
        # Independent PHP processes and sessions compete for one winning record.
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            jobs = [pool.submit(request, 'admin.php?action=quick_expense', dict(quick, record_id=2)), pool.submit(request2, 'admin.php?action=quick_expense', dict(quick, record_id=2, csrf_token=token2))]
            results = [json.loads(job.result()) for job in jobs]
        assert sum(result['success'] for result in results) == 1, results
        assert len(sql("SELECT id FROM expense_records WHERE reason='由抽奖记录 #2 快速报销生成'")) == 1
        # Audit-write failure must roll back the expense and discard its new upload.
        before = len(sql('SELECT * FROM expense_records'))
        uploads_before = set((site / 'uploads' / 'expenses').iterdir())
        sql('RENAME TABLE expense_edit_history TO expense_edit_history_backup')
        request('expense.php', fields, b'%PDF-1.4\n%%EOF')
        assert len(sql('SELECT * FROM expense_records')) == before
        assert set((site / 'uploads' / 'expenses').iterdir()) == uploads_before
        sql('RENAME TABLE expense_edit_history_backup TO expense_edit_history')
        history = json.loads(request(f'expense_ajax.php?action=history&id={expense_id}'))
        assert history['success'] and len(history['history']) == 4
        # No stale prefix-based match remains in rendered history.
        for page in ['expense.php', 'admin.php', 'index.php', 'history.php']:
            html = request(page)
            assert 'Fatal error' not in html and 'Warning:' not in html, page
            if shutil.which('node'):
                for script in re.findall(r'<script\b[^>]*>(.*?)</script>', html, re.S):
                    subprocess.run(['node', '-e', "new Function(require('fs').readFileSync(0, 'utf8'))"], input=script.encode(), check=True, capture_output=True)
        # Detail totals must cover all history even though only 50 rows are shown.
        sql("INSERT INTO lottery_records(user_id,project_id,prize_id,ip_address) VALUES " + ','.join(["(1,1,1,'127.0.0.1')"] * 55))
        sql("UPDATE users SET name='测试 <b>用户</b>' WHERE id=1")
        detail = json.loads(request('admin.php', dict(action='get_user_detail', user_id=1)))
        assert detail['success'] and '&lt;b&gt;用户&lt;/b&gt;' in detail['info_html']
        assert '<strong>58</strong>' in detail['info_html'] and '<strong>55</strong>' in detail['info_html']
        assert detail['html'].count('class="ud-record"') == 50
        assert all('id="ud-' + name + '"' in detail['html'] for name in ['projects', 'records', 'activity'])
        if shutil.which('node'):
            for handler in re.findall(r'onclick="([^"]*)"', detail['html']):
                subprocess.run(['node', '-e', "new Function(require('fs').readFileSync(0, 'utf8'))"], input=html_module.unescape(handler).encode(), check=True, capture_output=True)
        print('PASS: MySQL reimbursement workflow, user detail totals/escaping, attachments, CSRF, validation, resubmission, approval audit, duplicate/concurrent claims and rollback')
    except Exception:
        print('Test artifacts:', work)
        for log in logs:
            log.flush()
        for file in work.glob('*.log'):
            print(file.name, file.read_text(errors='replace')[-4000:])
        raise
    finally:
        for process in reversed(processes):
            process.terminate()
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=5)
        for log in logs:
            log.close()
        # Resolve and constrain the recursive cleanup to this run's temporary directory.
        assert work.parent == temp_parent and work.name.startswith('expense-test-')
        shutil.rmtree(work)


if __name__ == '__main__':
    main()
