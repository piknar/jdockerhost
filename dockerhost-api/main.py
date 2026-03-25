from fastapi import FastAPI, HTTPException, Depends, Header
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional, List
import docker
import json
import os
import re
import subprocess
import shutil

app = FastAPI(title="DockerHost API", version="1.1.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

API_TOKEN = os.environ.get("DOCKERHOST_API_TOKEN", "dockerhost-secret-token-2026")
client = docker.from_env()

def verify_token(authorization: Optional[str] = Header(None)):
    if not authorization or authorization != f"Bearer {API_TOKEN}":
        raise HTTPException(status_code=401, detail="Unauthorized")
    return True

# ---- Models ----

class DeployRequest(BaseModel):
    name: str
    image: str
    port: Optional[int] = None
    env: Optional[List[str]] = []       # list of KEY=value strings
    env_vars: Optional[dict] = {}       # legacy dict format
    restart_policy: Optional[str] = "unless-stopped"
    subdomain: Optional[str] = None

class PullRequest(BaseModel):
    image: str

class SubdomainRequest(BaseModel):
    subdomain: str
    target_port: int
    container_name: str
    base_domain: str = "ymmude.com"

# ---- Config paths ----

NGINX_CONF     = "/etc/nginx/sites-available/ymmude-all"
LSWS_CONF      = "/usr/local/lsws/conf/httpd_config.conf"
LSWS_VHOSTS    = "/usr/local/lsws/conf/vhosts"
SSL_CERT       = "/etc/letsencrypt/live/ymmude.com-0001/fullchain.pem"
SSL_KEY        = "/etc/letsencrypt/live/ymmude.com-0001/privkey.pem"

# ---- Subdomain helpers ----

def build_nginx_block(fqdn: str, target_port: int) -> str:
    return (
        "\n# dockerhost-managed: " + fqdn + "\n"
        "server {\n"
        "    listen 443 ssl http2;\n"
        "    server_name " + fqdn + ";\n"
        "    ssl_certificate " + SSL_CERT + ";\n"
        "    ssl_certificate_key " + SSL_KEY + ";\n"
        "    ssl_protocols TLSv1.2 TLSv1.3;\n"
        "    ssl_prefer_server_ciphers on;\n"
        "    client_max_body_size 256M;\n"
        "    location / {\n"
        "        proxy_pass http://127.0.0.1:" + str(target_port) + ";\n"
        "        proxy_http_version 1.1;\n"
        "        proxy_set_header Host $host;\n"
        "        proxy_set_header X-Real-IP $remote_addr;\n"
        "        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;\n"
        "        proxy_set_header X-Forwarded-Proto https;\n"
        "        proxy_set_header Upgrade $http_upgrade;\n"
        '        proxy_set_header Connection "upgrade";\n'
        "        proxy_read_timeout 300;\n"
        "        proxy_send_timeout 300;\n"
        "    }\n"
        "}\n"
    )

def build_lsws_vhost_conf(fqdn: str, target_port: int) -> str:
    return (
        "docRoot                   /var/www/" + fqdn + "\n"
        "vhDomain                  " + fqdn + "\n"
        "\n"
        "vhssl {\n"
        "  keyFile                 " + SSL_KEY + "\n"
        "  certFile                " + SSL_CERT + "\n"
        "  certChain               1\n"
        "}\n"
        "\n"
        "accessControl {\n"
        "  allow                   *\n"
        "}\n"
        "\n"
        "context / {\n"
        "  type                    proxy\n"
        "  handler                 http://127.0.0.1:" + str(target_port) + "\n"
        "  addDefaultCharset       off\n"
        "  accessControl {\n"
        "    allow               *\n"
        "  }\n"
        "}\n"
    )

def build_lsws_vhost_block(fqdn: str) -> str:
    return (
        "\nvirtualhost " + fqdn + " {\n"
        "  vhRoot                  /var/www/" + fqdn + "\n"
        "  configFile              $SERVER_ROOT/conf/vhosts/" + fqdn + "/vhost.conf\n"
        "  allowSymbolLink         1\n"
        "  enableScript            1\n"
        "  restrained              0\n"
        "}\n"
    )

def lsws_add_map_and_vhost(fqdn: str) -> None:
    """Insert map line into HTTPS listener and append virtualhost block."""
    with open(LSWS_CONF, 'r') as f:
        content = f.read()
    lines = content.split('\n')

    # Find closing } of listener HTTPS block and insert map line before it
    in_https = False
    depth = 0
    insert_at = -1
    for i, line in enumerate(lines):
        if not in_https:
            if 'listener HTTPS {' in line:
                in_https = True
                depth = line.count('{') - line.count('}')
        else:
            depth += line.count('{') - line.count('}')
            if depth <= 0:
                insert_at = i
                break

    map_line = "    map                     " + fqdn + " " + fqdn
    if insert_at >= 0:
        lines.insert(insert_at, map_line)

    # Append virtualhost block at end
    new_content = '\n'.join(lines) + build_lsws_vhost_block(fqdn)
    with open(LSWS_CONF, 'w') as f:
        f.write(new_content)

def lsws_remove_map_and_vhost(fqdn: str) -> None:
    """Remove map line from HTTPS listener and remove virtualhost block."""
    with open(LSWS_CONF, 'r') as f:
        content = f.read()
    lines = content.split('\n')

    # Remove map line (inside HTTPS listener)
    map_pattern = re.compile(r'^\s*map\s+' + re.escape(fqdn) + r'\s+' + re.escape(fqdn) + r'\s*$')
    lines = [l for l in lines if not map_pattern.match(l)]

    # Remove virtualhost block
    new_lines = []
    skip = False
    depth = 0
    vhost_pattern = 'virtualhost ' + fqdn + ' {'
    for line in lines:
        if not skip:
            if vhost_pattern in line:
                skip = True
                depth = line.count('{') - line.count('}')
                continue
            new_lines.append(line)
        else:
            depth += line.count('{') - line.count('}')
            if depth <= 0:
                skip = False

    with open(LSWS_CONF, 'w') as f:
        f.write('\n'.join(new_lines))

def nginx_remove_block(fqdn: str) -> None:
    """Remove the dockerhost-managed server block for fqdn from nginx config."""
    with open(NGINX_CONF, 'r') as f:
        content = f.read()
    lines = content.split('\n')

    new_lines = []
    skip = False
    depth = 0
    marker = '# dockerhost-managed: ' + fqdn
    i = 0
    while i < len(lines):
        line = lines[i]
        if not skip:
            if line.strip() == marker:
                # Skip comment line + following server block
                skip = True
                i += 1
                continue
            new_lines.append(line)
        else:
            # Looking for start of server block
            if '{' in line:
                depth += line.count('{') - line.count('}')
                if depth <= 0:
                    skip = False  # single-line block (unlikely)
            elif depth > 0:
                depth += line.count('{') - line.count('}')
                if depth <= 0:
                    skip = False
        i += 1

    # If we started counting (depth was set), continue until depth==0
    with open(NGINX_CONF, 'w') as f:
        f.write('\n'.join(new_lines))

def nginx_remove_block_v2(fqdn: str) -> None:
    """Robust removal: find marker comment, then remove comment + server {...} block."""
    with open(NGINX_CONF, 'r') as f:
        content = f.read()

    marker = '# dockerhost-managed: ' + fqdn
    start_idx = content.find(marker)
    if start_idx == -1:
        return  # not found, nothing to do

    # Find 'server {' after the marker
    server_start = content.find('server {', start_idx)
    if server_start == -1:
        return

    # Track braces to find end of server block
    depth = 0
    pos = server_start
    end_idx = len(content)
    while pos < len(content):
        ch = content[pos]
        if ch == '{':
            depth += 1
        elif ch == '}':
            depth -= 1
            if depth == 0:
                end_idx = pos + 1
                break
        pos += 1

    # Remove from marker to end of server block (include trailing newline)
    if end_idx < len(content) and content[end_idx] == '\n':
        end_idx += 1

    new_content = content[:start_idx] + content[end_idx:]
    with open(NGINX_CONF, 'w') as f:
        f.write(new_content)

def reload_nginx() -> str:
    r = subprocess.run(['nginx', '-t'], capture_output=True, text=True)
    if r.returncode != 0:
        raise RuntimeError('nginx -t failed: ' + r.stderr)
    subprocess.run(['nginx', '-s', 'reload'], check=True)
    return 'nginx reloaded'

def restart_lsws() -> str:
    r = subprocess.run(['/usr/local/lsws/bin/lswsctrl', 'restart'], capture_output=True, text=True)
    return r.stdout.strip() or r.stderr.strip() or 'lsws restarted'

def list_managed_subdomains() -> list:
    """Parse nginx config and return list of dockerhost-managed subdomains."""
    if not os.path.exists(NGINX_CONF):
        return []
    with open(NGINX_CONF, 'r') as f:
        content = f.read()
    pattern = re.compile(r'# dockerhost-managed: (\S+)')
    return pattern.findall(content)

# ---- CONTAINERS ----

@app.get("/containers")
def list_containers(auth=Depends(verify_token)):
    containers = client.containers.list(all=True)
    result = []
    for c in containers:
        result.append({
            "id": c.short_id,
            "name": c.name,
            "image": c.image.tags[0] if c.image.tags else c.image.short_id,
            "status": c.status,
            "ports": c.ports,
            "created": c.attrs["Created"],
            "labels": c.labels,
        })
    return result

@app.post("/containers/deploy")
def deploy_container(req: DeployRequest, auth=Depends(verify_token)):
    try:
        port_bindings = {}
        if req.port:
            port_bindings[req.port] = None

        # Combine env sources
        env_list = list(req.env or [])
        if req.env_vars:
            env_list += [f"{k}={v}" for k, v in req.env_vars.items()]

        container = client.containers.run(
            image=req.image,
            name=req.name,
            detach=True,
            environment=env_list,
            ports=port_bindings if req.port else None,
            restart_policy={"Name": req.restart_policy},
            labels={"dockerhost": "true", "subdomain": req.subdomain or ""}
        )

        # Reload to get assigned host port
        container.reload()
        host_port = None
        if req.port and container.ports:
            bindings = container.ports.get(f"{req.port}/tcp", [])
            if bindings:
                host_port = int(bindings[0]['HostPort'])

        return {
            "id": container.short_id,
            "name": container.name,
            "status": container.status,
            "host_port": host_port,
            "message": "Container deployed successfully"
        }
    except docker.errors.ImageNotFound:
        raise HTTPException(status_code=404, detail=f"Image {req.image} not found. Pull it first.")
    except docker.errors.APIError as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/containers/{container_id}")
def get_container(container_id: str, auth=Depends(verify_token)):
    try:
        c = client.containers.get(container_id)
        host_port = None
        for port_key, bindings in (c.ports or {}).items():
            if bindings:
                host_port = int(bindings[0]['HostPort'])
                break
        return {
            "id": c.short_id,
            "name": c.name,
            "image": c.image.tags[0] if c.image.tags else c.image.short_id,
            "status": c.status,
            "ports": c.ports,
            "host_port": host_port,
            "created": c.attrs["Created"],
            "labels": c.labels,
            "env": c.attrs["Config"]["Env"],
        }
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Container not found")

@app.post("/containers/{container_id}/start")
def start_container(container_id: str, auth=Depends(verify_token)):
    try:
        c = client.containers.get(container_id)
        c.start()
        return {"status": "started", "id": container_id}
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Container not found")

@app.post("/containers/{container_id}/stop")
def stop_container(container_id: str, auth=Depends(verify_token)):
    try:
        c = client.containers.get(container_id)
        c.stop()
        return {"status": "stopped", "id": container_id}
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Container not found")

@app.post("/containers/{container_id}/restart")
def restart_container(container_id: str, auth=Depends(verify_token)):
    try:
        c = client.containers.get(container_id)
        c.restart()
        return {"status": "restarted", "id": container_id}
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Container not found")

@app.delete("/containers/{container_id}")
def delete_container(container_id: str, force: bool = False, auth=Depends(verify_token)):
    try:
        c = client.containers.get(container_id)
        c.remove(force=force)
        return {"status": "deleted", "id": container_id}
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Container not found")

@app.get("/containers/{container_id}/logs")
def get_logs(container_id: str, tail: int = 100, auth=Depends(verify_token)):
    try:
        c = client.containers.get(container_id)
        logs = c.logs(tail=tail, timestamps=True).decode("utf-8", errors="replace")
        return {"logs": logs}
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Container not found")

@app.get("/containers/{container_id}/stats")
def get_stats(container_id: str, auth=Depends(verify_token)):
    try:
        c = client.containers.get(container_id)
        stats = c.stats(stream=False)
        cpu_delta    = stats["cpu_stats"]["cpu_usage"]["total_usage"] - stats["precpu_stats"]["cpu_usage"]["total_usage"]
        system_delta = stats["cpu_stats"].get("system_cpu_usage", 0) - stats["precpu_stats"].get("system_cpu_usage", 0)
        cpu_percent  = (cpu_delta / system_delta * 100) if system_delta > 0 else 0
        mem_usage    = stats["memory_stats"].get("usage", 0)
        mem_limit    = stats["memory_stats"].get("limit", 1)
        return {
            "cpu_percent":      round(cpu_percent, 2),
            "memory_mb":        round(mem_usage / 1024 / 1024, 2),
            "memory_limit_mb":  round(mem_limit / 1024 / 1024, 2),
            "memory_percent":   round(mem_usage / mem_limit * 100, 2),
        }
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Container not found")

# ---- IMAGES ----

@app.get("/images")
def list_images(auth=Depends(verify_token)):
    images = client.images.list()
    return [{
        "id":      img.short_id,
        "tags":    img.tags,
        "size_mb": round(img.attrs["Size"] / 1024 / 1024, 2),
        "created": img.attrs["Created"],
    } for img in images]

@app.post("/images/pull")
def pull_image(req: PullRequest, auth=Depends(verify_token)):
    try:
        image = client.images.pull(req.image)
        return {"status": "pulled", "id": image.short_id, "tags": image.tags}
    except docker.errors.APIError as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.delete("/images/{image_id}")
def delete_image(image_id: str, force: bool = False, auth=Depends(verify_token)):
    try:
        client.images.remove(image_id, force=force)
        return {"status": "deleted", "id": image_id}
    except docker.errors.NotFound:
        raise HTTPException(status_code=404, detail="Image not found")

# ---- SUBDOMAINS ----

@app.get("/subdomains")
def list_subdomains(auth=Depends(verify_token)):
    subs = list_managed_subdomains()
    return {"subdomains": subs, "count": len(subs)}

@app.post("/subdomains/create")
def create_subdomain(req: SubdomainRequest, auth=Depends(verify_token)):
    fqdn = req.subdomain + "." + req.base_domain
    errors = []

    # Idempotency: if already exists, update and re-apply
    existing = list_managed_subdomains()
    if fqdn in existing:
        # Remove existing first, then re-create with new port
        try:
            nginx_remove_block_v2(fqdn)
        except Exception:
            pass
        try:
            lsws_remove_map_and_vhost(fqdn)
        except Exception:
            pass

    # 1. Append nginx server block
    try:
        with open(NGINX_CONF, 'a') as f:
            f.write(build_nginx_block(fqdn, req.target_port))
    except Exception as e:
        errors.append("nginx config: " + str(e))

    # 2. Create LiteSpeed vhost directory + config
    vhost_dir = os.path.join(LSWS_VHOSTS, fqdn)
    try:
        os.makedirs(vhost_dir, exist_ok=True)
        with open(os.path.join(vhost_dir, 'vhost.conf'), 'w') as f:
            f.write(build_lsws_vhost_conf(fqdn, req.target_port))
    except Exception as e:
        errors.append("lsws vhost conf: " + str(e))

    # 3. Create docroot
    try:
        os.makedirs("/var/www/" + fqdn, exist_ok=True)
    except Exception as e:
        errors.append("docroot: " + str(e))

    # 4. Update httpd_config.conf
    try:
        lsws_add_map_and_vhost(fqdn)
    except Exception as e:
        errors.append("httpd_config: " + str(e))

    # 5. Reload nginx
    try:
        reload_nginx()
    except Exception as e:
        errors.append("nginx reload: " + str(e))

    # 6. Restart LiteSpeed
    try:
        restart_lsws()
    except Exception as e:
        errors.append("lsws restart: " + str(e))

    return {
        "status":    "created" if not errors else "partial",
        "url":       "https://" + fqdn,
        "subdomain": fqdn,
        "errors":    errors,
    }

@app.delete("/subdomains/{subdomain}")
def delete_subdomain(subdomain: str, base_domain: str = "ymmude.com", auth=Depends(verify_token)):
    fqdn = subdomain + "." + base_domain
    errors = []

    # 1. Remove nginx block
    try:
        nginx_remove_block_v2(fqdn)
    except Exception as e:
        errors.append("nginx block removal: " + str(e))

    # 2. Remove LiteSpeed vhost dir
    vhost_dir = os.path.join(LSWS_VHOSTS, fqdn)
    try:
        if os.path.exists(vhost_dir):
            shutil.rmtree(vhost_dir)
    except Exception as e:
        errors.append("lsws vhost dir: " + str(e))

    # 3. Update httpd_config.conf
    try:
        lsws_remove_map_and_vhost(fqdn)
    except Exception as e:
        errors.append("httpd_config: " + str(e))

    # 4. Reload nginx
    try:
        reload_nginx()
    except Exception as e:
        errors.append("nginx reload: " + str(e))

    # 5. Restart LiteSpeed
    try:
        restart_lsws()
    except Exception as e:
        errors.append("lsws restart: " + str(e))

    return {
        "status": "deleted" if not errors else "partial",
        "subdomain": fqdn,
        "errors": errors,
    }

# ---- HEALTH ----

@app.get("/health")
def health():
    try:
        client.ping()
        return {"status": "ok", "docker": "connected", "version": "1.1.0"}
    except Exception as e:
        return {"status": "error", "docker": str(e)}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=7001)
