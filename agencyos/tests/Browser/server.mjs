// Local browser-test transport. Runs the real Laravel entrypoint via PHP CGI.
// Used on Windows hosts where PHP's built-in HTTP server cannot bind a port.
import http from 'node:http';
import { execFile } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const port = Number(process.env.BROWSER_PORT ?? 8099);
const publicRoot = path.join(root, 'public');
const php = process.env.BROWSER_PHP_CGI ?? path.resolve(root, '../.tools/php/php-cgi.exe');
const types = { '.css': 'text/css', '.js': 'text/javascript', '.woff': 'font/woff', '.woff2': 'font/woff2', '.png': 'image/png', '.svg': 'image/svg+xml' };

http.createServer(async (req, res) => {
    try {
        const url = new URL(req.url, `http://127.0.0.1:${port}`);
        if (url.pathname.startsWith('/build/')) {
            const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
            if (!file.startsWith(path.join(publicRoot, 'build') + path.sep)) { res.writeHead(404).end(); return; }
            res.setHeader('Content-Type', types[path.extname(file)] ?? 'application/octet-stream');
            res.end(await readFile(file)); return;
        }
        const chunks = []; let size = 0;
        for await (const chunk of req) { size += chunk.length; if (size > 1048576) { res.writeHead(413).end(); return; } chunks.push(chunk); }
        const body = Buffer.concat(chunks);
        const env = { ...process.env, REDIRECT_STATUS: '200', GATEWAY_INTERFACE: 'CGI/1.1', SERVER_PROTOCOL: 'HTTP/1.1', SERVER_SOFTWARE: 'AgencyOS-browser-tests', SERVER_NAME: '127.0.0.1', SERVER_PORT: String(port), REMOTE_ADDR: '127.0.0.1', DOCUMENT_ROOT: publicRoot, SCRIPT_FILENAME: path.join(publicRoot, 'index.php'), SCRIPT_NAME: '/index.php', PHP_SELF: '/index.php', REQUEST_METHOD: req.method, REQUEST_URI: req.url, QUERY_STRING: url.search.slice(1), CONTENT_TYPE: req.headers['content-type'] ?? '', CONTENT_LENGTH: String(body.length) };
        for (const [name, value] of Object.entries(req.headers)) { env['HTTP_' + name.toUpperCase().replaceAll('-', '_')] = Array.isArray(value) ? value.join(', ') : value; }
        const child = execFile(php, [], { cwd: root, env, encoding: 'buffer', timeout: 30000, maxBuffer: 4 * 1024 * 1024 }, (error, stdout) => {
            if (error) { res.writeHead(500).end('Browser test PHP transport failed.'); return; }
            const separator = stdout.indexOf('\r\n\r\n');
            if (separator < 0) { res.writeHead(500).end('Invalid PHP CGI response.'); return; }
            const cookies = [];
            for (const line of stdout.subarray(0, separator).toString().split('\r\n')) {
                const colon = line.indexOf(':'); if (colon < 0) { continue; }
                const name = line.slice(0, colon); const value = line.slice(colon + 1).trim();
                if (name.toLowerCase() === 'status') { res.statusCode = Number(value.split(' ')[0]); }
                else if (name.toLowerCase() === 'set-cookie') { cookies.push(value); }
                else { res.setHeader(name, value); }
            }
            if (cookies.length) { res.setHeader('Set-Cookie', cookies); }
            res.end(stdout.subarray(separator + 4));
        });
        child.stdin.end(body);
    } catch { if (!res.headersSent) { res.writeHead(404); } res.end('Not found.'); }
}).listen(port, '127.0.0.1', () => process.stdout.write(`Browser-test CGI transport listening on 127.0.0.1:${port}\n`));
