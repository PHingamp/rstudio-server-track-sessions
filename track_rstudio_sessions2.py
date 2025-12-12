import subprocess
import sqlite3
import time
import re
import os

DB_FILE = '/path/to/user_sessions.db'

def parse_ps_line(line):
    parts = re.split(r'\s+', line.strip(), maxsplit=10)
    user = parts[0]
    pid = parts[1]
    cpu = parts[2]
    mem = parts[3]
    time = parts[9]
    start = parts[8]
    return user, pid, time, start, cpu, mem

def init():
    conn = sqlite3.connect(DB_FILE)
    cursor = conn.cursor()

    # Create tables if they don't exist, minutes is incremented every minute an rsession process is observed
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS user_totals (
            user TEXT PRIMARY KEY,
            minutes INTEGER DEFAULT 0,
            sessions INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1
        )
    ''')
    # cpu and mem will record the max observed values. minutes is incremented every minute this specific process is seen
    # In the context of a docker container, the hostname is a unique ID that changes at each container restart (needed
    # because PIDs aren't unique across restarts)
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS user_sessions (
            hostname TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            user TEXT,
            pid TEXT,
            time TEXT,
            start TEXT,
            minutes INTEGER DEFAULT 1,
            cpu REAL DEFAULT 0,
            mem REAL DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            PRIMARY KEY (hostname, pid)
        )
    ''')
    conn.commit()
    conn.close()

def update_user_sessions():
    ps_output = subprocess.check_output(['ps', 'aux']).decode('utf-8')
    conn = sqlite3.connect(DB_FILE)
    cursor = conn.cursor()
    cursor.execute('UPDATE user_totals SET is_active = 0 WHERE is_active = 1')
    cursor.execute('UPDATE user_sessions SET is_active = 0 WHERE is_active = 1')

    for line in ps_output.splitlines():
        if '/bin/rsession -u' in line:
            user, pid, time, start, cpu, mem = parse_ps_line(line)

            # Update user_totals
            cursor.execute('INSERT OR IGNORE INTO user_totals (user,minutes) VALUES (?,0)', (user,))
            cursor.execute('UPDATE user_totals SET minutes = minutes + 1, is_active=1 WHERE user = ?', (user,))
            # Check if this user's rsession exists
            cursor.execute('SELECT user FROM user_sessions WHERE hostname = ? AND pid = ?', (hostname, pid))
            if cursor.fetchone() is not None:
                cursor.execute('''
                UPDATE user_sessions SET
                    minutes = minutes +1,
                    time = ?,
                    cpu = ( SELECT MAX(cpu, ?) ),
                    mem = ( SELECT MAX(mem, ?) ),
                    is_active = 1
                WHERE hostname = ? AND pid = ?
                ''', (time, cpu, mem, hostname, pid))
            else:
                # Insert new record in user_sessions
                cursor.execute('''
                INSERT INTO user_sessions (hostname, pid, user, time, start, mem, cpu)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ''', (hostname, pid, user, time, start, mem, cpu))
                cursor.execute('UPDATE user_totals SET sessions = sessions + 1 WHERE user = ?', (user,))

    conn.commit()
    conn.close()
    #print(f"Updated at {time.strftime('%Y-%m-%d %H:%M:%S')}")

hostname = os.environ.get('HOSTNAME', 'wtf')
init()
# Run forever, every 60 seconds
while True:
    update_user_sessions()
    time.sleep(60)
