from urllib import parse, request
import json
import hashlib
import hmac

secret = b'1234'
data = json.dumps({"rfid":"123456"}).encode('utf8')
headers = {'X-SIGNATURE': hmac.new(secret, data, hashlib.sha512).hexdigest(), 'Content-Type': 'application/json'}



req = request.Request("http://localhost/endpoints/auth.php", data = data, headers = headers)

try:
    resp = request.urlopen(req)
    print(resp.read().decode('utf-8'))
except Exception as e:
    print(e)
