"""
Hirfaty media browser — a read-only window on the photo storage, kept out of
the website so browsing thousands of images never loads the app.

Two kinds of source, browsed the same way:

  * "live"     production's SeaweedFS, read through its filer HTTP API
               (FILER_URL) — the files the site serves right now.
  * a backup   any media.tar.gz under /backups (docker/scripts/backup.sh).
               "Ouvrir" extracts it into /work and starts a private SeaweedFS
               on that copy, bound to 127.0.0.1 inside this container, so its
               photos can be listed, viewed and downloaded; "Fermer" stops it
               and deletes the copy. The archive itself is mounted read-only
               and never modified.

Nothing here can write to production storage: the live filer is only ever
sent GET requests.

Started on demand (`make media-browser`) and published on the server's
loopback only — reach it through an SSH tunnel.
"""

import html
import json
import os
import shutil
import socket
import subprocess
import tarfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

FILER_URL = os.environ.get("FILER_URL", "http://seaweedfs:8888").rstrip("/")
BUCKET = os.environ.get("BUCKET", "hirfaty")
BACKUPS_DIR = os.environ.get("BACKUPS_DIR", "/backups")
WORK_DIR = os.environ.get("WORK_DIR", "/work")
WEED = os.environ.get("WEED", "/usr/bin/weed")
PORT = int(os.environ.get("PORT", "8090"))

ROOT = f"/buckets/{BUCKET}"
# A lost file stalls ~13s in SeaweedFS before failing; don't hang longer.
READ_TIMEOUT = 15
DIR_MODE = 1 << 31  # Go's os.ModeDir, as the filer reports it.
MAX_RECURSIVE = 5000

_lock = threading.Lock()
# backup name -> {"proc": Popen, "filer": "http://127.0.0.1:port", "dir": path}
_opened: dict = {}


# ── Sources ────────────────────────────────────────────────────────────────

def backups() -> list:
    """Every media archive under BACKUPS_DIR, newest first."""
    found = []
    if not os.path.isdir(BACKUPS_DIR):
        return found

    for entry in os.scandir(BACKUPS_DIR):
        # New layout: one dated folder per run holding media.tar.gz.
        if entry.is_dir() and os.path.isfile(os.path.join(entry.path, "media.tar.gz")):
            archive = os.path.join(entry.path, "media.tar.gz")
            found.append((entry.name, archive))
        # Older flat archives from before the combined backup.
        elif entry.is_file() and entry.name.startswith("hirfaty-media-") and entry.name.endswith(".tar.gz"):
            found.append((entry.name[: -len(".tar.gz")], entry.path))

    return [
        {"name": name, "archive": path, "size": os.path.getsize(path), "open": name in _opened}
        # Both layouts carry the same timestamp format; sort on it alone.
        for name, path in sorted(found, key=lambda b: b[0].removeprefix("hirfaty-media-"), reverse=True)
    ]


def filer_for(source: str) -> str:
    if source == "live":
        return FILER_URL
    with _lock:
        if source not in _opened:
            raise LookupError(f"La sauvegarde « {source} » n'est pas ouverte.")
        return _opened[source]["filer"]


def free_port() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def open_backup(name: str) -> None:
    archive = next((b["archive"] for b in backups() if b["name"] == name), None)
    if archive is None:
        raise LookupError(f"Sauvegarde « {name} » introuvable.")

    with _lock:
        if name in _opened:
            return

        target = os.path.join(WORK_DIR, name)
        shutil.rmtree(target, ignore_errors=True)
        os.makedirs(target)
        with tarfile.open(archive, "r:gz") as tar:
            try:
                tar.extractall(target, filter="data")
            except TypeError:  # Python < 3.12 has no extraction filters.
                tar.extractall(target)

        # gRPC listens on port + 10000, so keep the HTTP ports low enough.
        while True:
            master, volume, filer = free_port(), free_port(), free_port()
            if max(master, volume, filer) < 55000:
                break

        proc = subprocess.Popen(
            [
                WEED, "server",
                f"-dir={target}",
                "-ip=127.0.0.1", "-ip.bind=127.0.0.1",
                f"-master.port={master}",
                f"-volume.port={volume}",
                "-filer", f"-filer.port={filer}",
                "-master.volumeSizeLimitMB=1024",
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        _opened[name] = {"proc": proc, "filer": f"http://127.0.0.1:{filer}", "dir": target}

    # Ready once the filer can list the bucket and the volumes have registered.
    deadline = time.time() + 90
    while time.time() < deadline:
        if proc.poll() is not None:
            close_backup(name)
            raise RuntimeError("SeaweedFS n'a pas démarré sur cette sauvegarde.")
        try:
            listing(name, "")
            return
        except Exception:
            time.sleep(1)

    close_backup(name)
    raise RuntimeError("La sauvegarde n'a pas pu être ouverte à temps.")


def close_backup(name: str) -> None:
    with _lock:
        opened = _opened.pop(name, None)
    if not opened:
        return
    opened["proc"].terminate()
    try:
        opened["proc"].wait(timeout=15)
    except subprocess.TimeoutExpired:
        opened["proc"].kill()
    shutil.rmtree(opened["dir"], ignore_errors=True)


# ── Filer reads (GET only) ─────────────────────────────────────────────────

def filer_path(rel: str) -> str:
    rel = rel.strip("/")
    return urllib.parse.quote(f"{ROOT}/{rel}" if rel else ROOT)


def listing(source: str, rel: str) -> list:
    """One directory, every page of it."""
    entries, last = [], ""
    while True:
        query = urllib.parse.urlencode({"limit": 1000, "lastFileName": last})
        req = urllib.request.Request(
            f"{filer_for(source)}{filer_path(rel)}/?{query}",
            headers={"Accept": "application/json"},
        )
        with urllib.request.urlopen(req, timeout=READ_TIMEOUT) as resp:
            data = json.load(resp)

        batch = data.get("Entries") or []
        entries += [describe(e) for e in batch]
        last = data.get("LastFileName") or ""
        if not data.get("ShouldDisplayLoadMore") or not batch:
            return entries


def describe(entry: dict) -> dict:
    full = entry.get("FullPath", "")
    rel = full[len(ROOT):].strip("/") if full.startswith(ROOT) else full.strip("/")
    is_dir = bool(int(entry.get("Mode") or 0) & DIR_MODE)
    size = entry.get("FileSize")
    if not size:
        size = sum(int(c.get("size") or 0) for c in entry.get("chunks") or [])

    return {
        "path": rel,
        "name": rel.rsplit("/", 1)[-1],
        "dir": is_dir,
        "size": int(size or 0),
        "mtime": entry.get("Mtime") or "",
        "mime": entry.get("Mime") or "",
        "chunks": [c.get("file_id") or c.get("fid") for c in entry.get("chunks") or []],
    }


def walk(source: str, rel: str) -> list:
    files, stack = [], [rel]
    while stack and len(files) < MAX_RECURSIVE:
        for entry in listing(source, stack.pop()):
            if entry["dir"]:
                stack.append(entry["path"])
            else:
                files.append(entry)
    return sorted(files, key=lambda f: f["mtime"], reverse=True)


# ── Presentation ───────────────────────────────────────────────────────────

def size_label(n: int) -> str:
    if n < 1024 * 1024:
        return f"{max(1, round(n / 1024))} Ko"
    if n < 1024 ** 3:
        return f"{n / 1024 / 1024:.1f} Mo"
    return f"{n / 1024 ** 3:.2f} Go"


def context_label(rel: str) -> str:
    """What the platform keeps in that folder, read from the path itself."""
    parts = rel.split("/")
    if parts[0] == "products" and len(parts) > 1:
        label = f"Produit #{parts[1]}"
        if len(parts) > 3 and parts[2] == "variants":
            label += f" · déclinaison #{parts[3]}"
        return label
    return {
        "stores": f"Atelier #{parts[1]}" if len(parts) > 2 else "Ateliers",
        "categories": "Catégorie",
        "blogs": "Journal",
    }.get(parts[0], parts[0])


def esc(value) -> str:
    return html.escape(str(value), quote=True)


def link(route: str, **params) -> str:
    return esc(f"{route}?{urllib.parse.urlencode(params)}")


STYLE = """
body{font-family:system-ui,sans-serif;margin:0;background:#f6f6f4;color:#222}
header{background:#1d1d1b;color:#fff;padding:14px 24px}
header a{color:#fff;text-decoration:none;font-weight:600}
main{padding:24px}
h1{font-size:20px;margin:0 0 4px}
.muted{color:#777;font-size:13px}
.crumbs{margin:12px 0 18px;font-size:14px}.crumbs a{color:#a0522d}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px}
.card{background:#fff;border:1px solid #e3e3df;border-radius:10px;padding:8px;font-size:12px;overflow:hidden}
.card img,.ph{width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;background:#eee;display:block}
.ph{display:flex;align-items:center;justify-content:center;font-size:34px;color:#a0522d;text-decoration:none}
.card .n{font-family:monospace;font-size:10px;color:#666;word-break:break-all;margin-top:4px}
.bad{color:#c0392b;font-weight:600}
table{border-collapse:collapse;background:#fff;width:100%;max-width:900px}
td,th{border-bottom:1px solid #eee;padding:8px 10px;text-align:left;font-size:14px}
button,.btn{background:#1d1d1b;color:#fff;border:0;border-radius:6px;padding:6px 12px;cursor:pointer;font-size:13px;text-decoration:none}
.btn.light{background:#e9e9e5;color:#222}
.err{background:#fdecea;color:#a12;padding:12px;border-radius:8px}
"""

SCRIPT = """
document.querySelectorAll('img[data-dims]').forEach(img => {
  const out = document.getElementById(img.dataset.dims);
  const done = () => out.textContent = img.naturalWidth + '×' + img.naturalHeight + ' px';
  img.complete && img.naturalWidth ? done() : img.addEventListener('load', done);
  img.addEventListener('error', () => { out.textContent = 'Illisible'; out.className = 'bad'; });
});
"""


def page(title: str, body: str) -> bytes:
    return (
        "<!doctype html><html lang=fr><meta charset=utf-8>"
        "<meta name=viewport content='width=device-width,initial-scale=1'>"
        f"<title>{esc(title)} — Hirfaty médias</title><style>{STYLE}</style>"
        "<header><a href='/'>Hirfaty · navigateur de médias</a></header>"
        f"<main>{body}</main><script>{SCRIPT}</script></html>"
    ).encode()


def source_name(source: str) -> str:
    return "Production (en direct)" if source == "live" else f"Sauvegarde {source}"


def file_card(source: str, f: dict, i: int, show_path: bool) -> str:
    view = link("/file", src=source, path=f["path"])
    download = link("/file", src=source, path=f["path"], download=1)
    is_image = f["mime"].startswith("image/") or f["name"].lower().endswith((".jpg", ".jpeg", ".png", ".webp", ".gif"))
    thumb = (
        f"<a href='{view}' target=_blank><img loading=lazy src='{view}' data-dims='d{i}' alt=''></a>"
        if is_image else f"<a class=ph href='{view}' target=_blank>📄</a>"
    )
    when = f["mtime"][:16].replace("T", " ")
    return (
        f"<div class=card>{thumb}"
        f"<div><b>{esc(context_label(f['path']))}</b></div>"
        f"<div>{size_label(f['size'])} · <span id=d{i}>{'—' if is_image else esc(f['mime'])}</span></div>"
        f"<div class=muted>{esc(when)}</div>"
        f"<div class=n title='{esc(', '.join(c for c in f['chunks'] if c))}'>{esc(f['path'] if show_path else f['name'])}</div>"
        f"<div style='margin-top:6px'><a class='btn light' href='{download}'>Télécharger</a></div></div>"
    )


def crumbs(source: str, rel: str) -> str:
    out = [f"<a href='{link('/browse', src=source)}'>{esc(BUCKET)}</a>"]
    acc = ""
    for part in [p for p in rel.split("/") if p]:
        acc = f"{acc}/{part}".strip("/")
        out.append(f"<a href='{link('/browse', src=source, dir=acc)}'>{esc(part)}</a>")
    return "<div class=crumbs>" + " / ".join(out) + "</div>"


# ── HTTP ───────────────────────────────────────────────────────────────────

class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def params(self) -> dict:
        query = urllib.parse.urlparse(self.path).query
        return {k: v[-1] for k, v in urllib.parse.parse_qs(query).items()}

    def send_html(self, body: bytes, status: int = 200):
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def redirect(self, to: str):
        self.send_response(303)
        self.send_header("Location", to)
        self.end_headers()

    def fail(self, message: str, status: int = 500):
        self.send_html(page("Erreur", f"<p class=err>{esc(message)}</p><p><a href='/'>Retour</a></p>"), status)

    def do_GET(self):
        route = urllib.parse.urlparse(self.path).path
        p = self.params()
        try:
            if route == "/":
                return self.home()
            if route == "/browse":
                return self.browse(p.get("src", "live"), p.get("dir", ""))
            if route == "/all":
                return self.all(p.get("src", "live"), p.get("dir", ""))
            if route == "/file":
                return self.file(p.get("src", "live"), p.get("path", ""), p.get("download") == "1")
            return self.fail("Page introuvable.", 404)
        except LookupError as e:
            return self.fail(str(e), 404)
        except (urllib.error.URLError, TimeoutError, OSError) as e:
            return self.fail(f"Stockage injoignable : {e}", 502)

    def do_POST(self):
        route = urllib.parse.urlparse(self.path).path
        name = self.params().get("name", "")
        try:
            if route == "/open":
                open_backup(name)
                return self.redirect(f"/browse?{urllib.parse.urlencode({'src': name})}")
            if route == "/close":
                close_backup(name)
                return self.redirect("/")
            return self.fail("Page introuvable.", 404)
        except (LookupError, RuntimeError, OSError, tarfile.TarError) as e:
            return self.fail(str(e))

    def home(self):
        rows = "".join(
            "<tr>"
            f"<td>{esc(b['name'])}</td><td>{size_label(b['size'])}</td><td>"
            + (
                f"<a class=btn href='{link('/browse', src=b['name'])}'>Parcourir</a> "
                f"<form method=post action='{link('/close', name=b['name'])}' style='display:inline'>"
                "<button class='btn light'>Fermer</button></form>"
                if b["open"] else
                f"<form method=post action='{link('/open', name=b['name'])}' style='display:inline' "
                "onsubmit=\"this.querySelector('button').textContent='Ouverture… (1 min)'\">"
                "<button>Ouvrir</button></form>"
            )
            + "</td></tr>"
            for b in backups()
        ) or "<tr><td colspan=3 class=muted>Aucune sauvegarde trouvée dans /var/backups/hirfaty.</td></tr>"

        body = (
            "<h1>Photos de la plateforme</h1>"
            "<p class=muted>Lecture seule. Rien ici ne peut modifier le stockage de production.</p>"
            f"<p><a class=btn href='{link('/browse', src='live')}'>Parcourir la production (en direct)</a> "
            f"<a class='btn light' href='{link('/all', src='live')}'>Toutes les photos</a></p>"
            "<h1 style='margin-top:28px'>Sauvegardes</h1>"
            "<p class=muted>« Ouvrir » extrait une copie temporaire de la sauvegarde pour en voir les photos ; "
            "« Fermer » la supprime. L'archive elle-même n'est jamais modifiée.</p>"
            f"<table><tr><th>Sauvegarde</th><th>Photos</th><th></th></tr>{rows}</table>"
        )
        self.send_html(page("Accueil", body))

    def browse(self, source: str, rel: str):
        entries = listing(source, rel)
        dirs = [e for e in entries if e["dir"]]
        files = sorted((e for e in entries if not e["dir"]), key=lambda f: f["mtime"], reverse=True)

        folders = "".join(
            f"<a class=card style='text-decoration:none;color:inherit' href='{link('/browse', src=source, dir=d['path'])}'>"
            f"<div class=ph>📁</div><div><b>{esc(d['name'])}</b></div>"
            f"<div class=muted>{esc(context_label(d['path']))}</div></a>"
            for d in dirs
        )
        cards = "".join(file_card(source, f, i, False) for i, f in enumerate(files))
        total = sum(f["size"] for f in files)

        body = (
            f"<h1>{esc(source_name(source))}</h1>{crumbs(source, rel)}"
            f"<p class=muted>{len(dirs)} dossier(s), {len(files)} fichier(s) ici · {size_label(total)} · "
            f"<a href='{link('/all', src=source, dir=rel)}'>tout afficher, sous-dossiers compris</a></p>"
            f"<div class=grid>{folders}{cards}</div>"
        )
        self.send_html(page(source_name(source), body))

    def all(self, source: str, rel: str):
        files = walk(source, rel)
        by_folder: dict = {}
        for f in files:
            top = f["path"].split("/", 1)[0]
            count, size = by_folder.get(top, (0, 0))
            by_folder[top] = (count + 1, size + f["size"])

        summary = " · ".join(f"{esc(k)} : {c} ({size_label(s)})" for k, (c, s) in sorted(by_folder.items()))
        cards = "".join(file_card(source, f, i, True) for i, f in enumerate(files))
        body = (
            f"<h1>{esc(source_name(source))} — toutes les photos</h1>{crumbs(source, rel)}"
            f"<p class=muted>{len(files)} fichier(s) · {size_label(sum(f['size'] for f in files))}"
            f"{' (limité à ' + str(MAX_RECURSIVE) + ')' if len(files) >= MAX_RECURSIVE else ''}<br>{summary}</p>"
            "<p class=muted>Une photo marquée « Illisible » est enregistrée mais ses données sont perdues.</p>"
            f"<div class=grid>{cards}</div>"
        )
        self.send_html(page(source_name(source), body))

    def file(self, source: str, rel: str, download: bool):
        url = f"{filer_for(source)}{filer_path(rel)}"
        with urllib.request.urlopen(url, timeout=READ_TIMEOUT) as resp:
            self.send_response(200)
            self.send_header("Content-Type", resp.headers.get("Content-Type", "application/octet-stream"))
            if resp.headers.get("Content-Length"):
                self.send_header("Content-Length", resp.headers["Content-Length"])
            if download:
                name = rel.rsplit("/", 1)[-1]
                self.send_header("Content-Disposition", f'attachment; filename="{name}"')
            self.end_headers()
            shutil.copyfileobj(resp, self.wfile)


if __name__ == "__main__":
    os.makedirs(WORK_DIR, exist_ok=True)
    print(f"Hirfaty media browser on :{PORT} (live filer {FILER_URL})", flush=True)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()
