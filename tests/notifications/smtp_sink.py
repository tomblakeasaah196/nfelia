# A stand-in mail server for the notification tests: accepts every message
# and saves it as OUTDIR/1.eml, 2.eml, ...
#   python3 smtp_sink.py OUTDIR [CERT KEY] [PORT]
# With CERT and KEY it offers STARTTLS, which the app's mailer insists on
# unless MAIL_ENCRYPTION is "ssl". While a file named REFUSE exists in
# OUTDIR it turns every message away with a temporary error (451), the way
# a mail server that is down or overloaded does, so retries can be tested.
import os, socketserver, ssl, sys, threading

OUT = sys.argv[1]
CERT = sys.argv[2:4] if len(sys.argv) >= 4 else []
PORT = int(sys.argv[4]) if len(sys.argv) >= 5 else 2525
os.makedirs(OUT, exist_ok=True)
LOCK = threading.Lock()

class H(socketserver.StreamRequestHandler):
    def w(self, s):
        self.wfile.write((s + "\r\n").encode())

    def handle(self):
        self.w("220 sink")
        self.loop()

    def loop(self):
        data, msg, authing = False, [], False
        for line in self.rfile:
            t = line.decode("latin-1").rstrip("\r\n")
            if authing:
                authing = False
                self.w("235 ok")
                continue
            if data:
                if t == ".":
                    with LOCK:
                        n = len([f for f in os.listdir(OUT) if f.endswith(".eml")]) + 1
                        with open(os.path.join(OUT, f"{n}.eml"), "w", encoding="latin-1") as f:
                            f.write("\n".join(msg))
                    data, msg = False, []
                    self.w("250 ok")
                else:
                    msg.append(t[1:] if t.startswith("..") else t)
                continue
            u = t.upper()
            if u.startswith("EHLO") or u.startswith("HELO"):
                self.w("250-sink")
                if CERT and not isinstance(self.request, ssl.SSLSocket):
                    self.w("250-STARTTLS")
                self.w("250 AUTH PLAIN")
            elif u.startswith("STARTTLS") and CERT:
                self.w("220 go")
                self.wfile.flush()
                ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
                ctx.load_cert_chain(CERT[0], CERT[1])
                self.request = ctx.wrap_socket(self.request, server_side=True)
                self.rfile = self.request.makefile("rb")
                self.wfile = self.request.makefile("wb", buffering=0)
                return self.loop()
            elif u == "AUTH PLAIN":
                authing = True
                self.w("334 ")
            elif u.startswith("AUTH"):
                self.w("235 ok")
            elif u.startswith("RCPT") and os.path.exists(os.path.join(OUT, "REFUSE")):
                self.w("451 try again later")
            elif u.startswith("DATA"):
                data = True
                self.w("354 go")
            elif u.startswith("QUIT"):
                self.w("221 bye")
                return
            else:
                self.w("250 ok")

socketserver.ThreadingTCPServer.allow_reuse_address = True
socketserver.ThreadingTCPServer(("127.0.0.1", PORT), H).serve_forever()
