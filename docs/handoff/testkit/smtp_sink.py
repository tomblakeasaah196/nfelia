import socketserver, sys, ssl
class H(socketserver.StreamRequestHandler):
    def w(self, s): self.wfile.write((s + "\r\n").encode())
    def handle(self):
        self.w("220 sink"); self.loop(False, [])
    def loop(self, data, msg):
        for line in self.rfile:
            t = line.decode('latin-1').rstrip("\r\n")
            if data:
                if t == ".": data = False; open(sys.argv[1], 'w', encoding='latin-1').write("\n".join(msg)); self.w("250 ok"); continue
                msg.append(t); continue
            u = t.upper()
            if u.startswith("EHLO"): self.w("250-sink"); self.w("250-STARTTLS"); self.w("250 AUTH PLAIN")
            elif u.startswith("STARTTLS"):
                self.w("220 go"); self.wfile.flush()
                ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); ctx.load_cert_chain('/tmp/claude-0/-home-user-nfelia/42497d9a-7e96-589f-b97a-cab068479dab/scratchpad/sink.crt', '/tmp/claude-0/-home-user-nfelia/42497d9a-7e96-589f-b97a-cab068479dab/scratchpad/sink.key')
                self.request = ctx.wrap_socket(self.request, server_side=True)
                self.rfile = self.request.makefile('rb'); self.wfile = self.request.makefile('wb', buffering=0)
                return self.loop(data, msg)
            elif u == "AUTH PLAIN": self.w("334 "); self.authing = True
            elif u.startswith("AUTH") or getattr(self, "authing", False): self.authing = False; self.w("235 ok")
            elif u.startswith("DATA"): data = True; self.w("354 go")
            elif u.startswith("QUIT"): self.w("221 bye"); return
            else: self.w("250 ok")
socketserver.TCPServer.allow_reuse_address = True
socketserver.TCPServer(("127.0.0.1", 2525), H).handle_request()
