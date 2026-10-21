from http.server import BaseHTTPRequestHandler,HTTPServer
class Handler(BaseHTTPRequestHandler):
    def log_message(self,*args):pass
    def do_GET(self):
        if self.path not in ['/clean','/negative']:
            self.send_response(404);self.end_headers();return
        body=b'<!doctype html><html lang="en"><head><title>Fictional qualification</title></head><body><main><h1>Fixture only</h1></main></body></html>'
        self.send_response(200)
        self.send_header('Content-Type','text/html; charset=utf-8')
        self.send_header('Content-Security-Policy',"default-src 'none'; frame-ancestors 'none'")
        self.send_header('X-Frame-Options','DENY')
        self.send_header('Cache-Control','no-store')
        if self.path=='/clean':self.send_header('X-Content-Type-Options','nosniff')
        self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
HTTPServer(('0.0.0.0',8018),Handler).serve_forever()
