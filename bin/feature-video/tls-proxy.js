// Serves the local app over HTTPS as a real domain, so a video shows
// "adannaluxehair.nfelia.com" and not "localhost:8099". make.sh starts it when
// NF_VIDEO_DOMAIN is set; record.js points the domain at this machine.
//
//   node tls-proxy.js <domain> <app url, like http://localhost:8099> <port, 443>
//
// The certificate is made on the spot for the domain and *.domain and trusted
// by nobody: the filming browser is told to accept it.
const https = require('https');
const http = require('http');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const [domain, target, port = '443'] = process.argv.slice(2);
const to = new URL(target);

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'nf-tls-'));
execFileSync('openssl', ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '2', '-subj', `/CN=${domain}`,
  '-addext', `subjectAltName=DNS:${domain},DNS:*.${domain}`, '-keyout', `${dir}/key.pem`, '-out', `${dir}/cert.pem`], { stdio: 'ignore' });
const key = fs.readFileSync(`${dir}/key.pem`), cert = fs.readFileSync(`${dir}/cert.pem`);
fs.rmSync(dir, { recursive: true, force: true });

https.createServer({ key, cert }, (req, res) => {
  // The Host header goes through as the browser sent it: the app tells a shop
  // (name.domain) from the main site by it
  const up = http.request({ host: to.hostname, port: to.port || 80, method: req.method, path: req.url,
    headers: { ...req.headers, 'x-forwarded-proto': 'https' } }, (r) => {
    res.writeHead(r.statusCode, r.headers);
    r.pipe(res);
  });
  up.on('error', (e) => { res.writeHead(502); res.end(String(e)); });
  req.pipe(up);
}).listen(Number(port), '127.0.0.1', () => console.log(`[video] https://${domain} → ${target}`));
