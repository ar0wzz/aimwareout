from flask import Flask, request, jsonify
import json
import os
import time
import random
import string

app = Flask(__name__)

LICENSES_FILE = 'licenses.json'

def load_licenses():
    if not os.path.exists(LICENSES_FILE):
        return {}
    with open(LICENSES_FILE, 'r') as f:
        return json.load(f)

def save_licenses(data):
    with open(LICENSES_FILE, 'w') as f:
        json.dump(data, f, indent=2)

@app.route('/api', methods=['GET'])
def api():
    action = request.args.get('action', '')
    key = request.args.get('key', '')
    hwid = request.args.get('hwid', '')
    admin_key = request.args.get('adminKey', '')
    days = request.args.get('days', '30')
    
    licenses = load_licenses()
    
    # Validation
    if action == 'validate':
        clean_key = key.replace('-', '')
        
        if clean_key not in licenses:
            return jsonify({'success': False, 'reason': 'Invalid key'})
        
        license_data = licenses[clean_key]
        now = int(time.time())
        
        if license_data['expires'] > 0 and license_data['expires'] < now:
            return jsonify({'success': False, 'reason': 'Key expired'})
        
        if license_data.get('hwid') and license_data['hwid'] != hwid:
            return jsonify({'success': False, 'reason': 'Used on another PC'})
        
        if not license_data.get('hwid'):
            license_data['hwid'] = hwid
            save_licenses(licenses)
        
        return jsonify({'success': True, 'expires': license_data['expires']})
    
    # Admin - Creer une cle
    if action == 'admin_create':
        if admin_key != 'admin123':
            return jsonify({'error': 'Unauthorized'})
        
        days_int = int(days)
        duration_hex = hex(days_int)[2:].upper().zfill(4)
        random_part = ''.join(random.choices(string.ascii_uppercase + string.digits, k=12))
        raw_key = 'AIMWARE' + duration_hex + random_part
        formatted_key = '-'.join([raw_key[i:i+4] for i in range(0, len(raw_key), 4)])
        
        expires = 0 if days_int == 0 else int(time.time()) + (days_int * 86400)
        
        licenses[raw_key] = {
            'expires': expires,
            'hwid': '',
            'created': int(time.time())
        }
        save_licenses(licenses)
        
        return jsonify({'success': True, 'key': formatted_key})
    
    # Admin - Lister
    if action == 'admin_list':
        if admin_key != 'admin123':
            return jsonify({'error': 'Unauthorized'})
        
        result = []
        for k, v in licenses.items():
            result.append({
                'key': k,
                'expires': v['expires'],
                'hwid': v.get('hwid', ''),
                'created': v['created']
            })
        return jsonify(result)
    
    # Admin - Supprimer
    if action == 'admin_delete':
        if admin_key != 'admin123':
            return jsonify({'error': 'Unauthorized'})
        
        clean_key = key.replace('-', '')
        if clean_key in licenses:
            del licenses[clean_key]
            save_licenses(licenses)
            return jsonify({'success': True})
        return jsonify({'error': 'Key not found'})
    
    return jsonify({'error': 'Unknown action'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8080)
