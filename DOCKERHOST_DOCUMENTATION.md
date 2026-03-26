# DockerHost — Complete Documentation
**Version:** 1.2.0  
**Date:** 2026-03-25  
**Component:** `com_dockerhost`  
**Platform:** Joomla 6.x + VPS (Alpine/Debian Linux)

---

## Table of Contents
1. [System Overview](#1-system-overview)
2. [Architecture](#2-architecture)
3. [Dependencies](#3-dependencies)
4. [Pre-Installation Checklist](#4-pre-installation-checklist)
5. [Setup Walkthrough](#5-setup-walkthrough)
6. [Component Features](#6-component-features)
7. [User Guide](#7-user-guide)
8. [Admin Guide](#8-admin-guide)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. System Overview

DockerHost is a full-stack Docker container management system embedded inside Joomla 6. It enables website users (after registration) to deploy, manage, and access Docker containers directly from the browser — similar to **render.com** or **Coolify**, but integrated within your existing Joomla site.

### What It Does
- Registered users can **deploy Docker containers** from any public image
- Each container automatically receives a **dedicated SSL URL** (`https://yourdomain.com:9001`)
- Users manage only **their own containers** (start/stop/restart/delete)
- Administrators see and manage **all users' containers**
- Containers are accessible instantly — no DNS changes required

### Real-World Use Case
- A user registers on `jtest.ymmude.com`
- They log in and go to the Container Dashboard
- They deploy `piknar/d3b4:latest` with port 8080
- The system auto-assigns `https://ymmude.com:9002`
- The user clicks **Open (SSL)** and accesses their AI agent container

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                     VPS (168.231.72.66)                             │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    nginx / LiteSpeed                         │   │
│  │                                                             │   │
│  │  :443  → jtest.ymmude.com  → Joomla (LiteSpeed PHP 8.3)   │   │
│  │  :9001  → SSL Proxy        → Container A (port 34009)      │   │
│  │  :9002  → SSL Proxy        → Container B (port 34010)      │   │
│  │  :9003  → SSL Proxy        → Container C (port 34011)      │   │
│  └───────────────────────────┬─────────────────────────────────┘   │
│                              │                                      │
│  ┌───────────────────────────▼─────────────────────────────────┐   │
│  │           Joomla 6.1.0-beta3 (PHP 8.3)                      │   │
│  │           /var/www/jtest.ymmude.com/                        │   │
│  │                                                             │   │
│  │  com_dockerhost:                                           │   │
│  │  ├── Admin:  /administrator/ (all containers, full mgmt)   │   │
│  │  └── Site:   /index.php?option=com_dockerhost (per-user)   │   │
│  └───────────────────────────┬─────────────────────────────────┘   │
│                              │ HTTP (fsockopen)                     │
│  ┌───────────────────────────▼─────────────────────────────────┐   │
│  │        FastAPI Docker Service (Python)                       │   │
│  │        http://127.0.0.1:7001                                │   │
│  │        /opt/dockerhost-api/main.py                          │   │
│  │                                                             │   │
│  │  Endpoints:                                                 │   │
│  │  /health  /containers  /images  /ssl  /proxy               │   │
│  └───────────────────────────┬─────────────────────────────────┘   │
│                              │ Docker SDK                           │
│  ┌───────────────────────────▼─────────────────────────────────┐   │
│  │              Docker Engine                                   │   │
│  │              /var/run/docker.sock                            │   │
│  │                                                             │   │
│  │  Containers tagged with labels:                             │   │
│  │  dockerhost=true, user_id, username, ssl_port               │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### Data Flow: Container Deploy
```
User Browser
    │
    │ HTTPS POST (deploy form)
    ▼
Joomla site/DashboardController
    │
    │ HTTP POST (ApiHelper.php → fsockopen)
    ▼
FastAPI /containers/deploy
    │  ├─ Picks next SSL port (9001-9100)
    │  ├─ Adds ALLOWED_ORIGINS env var
    │  ├─ Runs container via Docker SDK
    │  ├─ Writes nginx SSL server block
    │  ├─ Reloads nginx
    │  └─ Opens UFW firewall port
    │
    ▼
Response: {ssl_url: "https://ymmude.com:9002", host_port: 34010}
    │
    ▼
User sees: "Open (SSL)" button → https://ymmude.com:9002
```

---

## 3. Dependencies

### 3.1 VPS / Server Requirements

| Dependency | Minimum Version | Purpose | Install Command |
|-----------|----------------|---------|----------------|
| **Linux OS** | Ubuntu 20.04 / Debian 11 / Alpine 3.16+ | Host OS | — |
| **Docker Engine** | 24.0+ | Container runtime | `apt install docker.io` |
| **nginx** | 1.18+ | SSL port proxying | `apt install nginx` |
| **UFW Firewall** | Any | Port management | `apt install ufw` |
| **Python** | 3.10+ | FastAPI service | `apt install python3 python3-pip python3-venv` |
| **systemd** | Any | Service management | (pre-installed) |
| **LetsEncrypt SSL cert** | Valid wildcard | HTTPS for containers | `certbot certonly` |

### 3.2 Python Dependencies (FastAPI Service)

File: `/opt/dockerhost-api/requirements.txt`

```txt
fastapi>=0.104.0
uvicorn[standard]>=0.24.0
docker>=6.1.0
python-dotenv>=1.0.0
pydantic>=2.0.0
```

Install:
```bash
cd /opt/dockerhost-api
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### 3.3 Joomla Requirements

| Dependency | Minimum Version | Notes |
|-----------|----------------|-------|
| **Joomla** | 6.0+ | Tested on 6.1.0-beta3 |
| **PHP** | 8.2+ | PHP 8.3 recommended |
| **PHP Extensions** | | |
| &nbsp;&nbsp;`sodium` | Any | Joomla 6 cryptography |
| &nbsp;&nbsp;`intl` | Any | Internationalization |
| &nbsp;&nbsp;`mbstring` | Any | String handling |
| &nbsp;&nbsp;`curl` | Any | HTTP requests |
| &nbsp;&nbsp;`json` | Any | API communication |
| &nbsp;&nbsp;`pdo_mysql` | Any | Database |
| **MySQL/MariaDB** | 8.0+ / 10.4+ | Database server |
| **Bootstrap** | 5.x | Included in Joomla 6 |
| **Font Awesome** | 5.x | Icons (loaded by component) |

### 3.4 Critical PHP Configuration

The following functions **must NOT be in `disable_functions`** in PHP ini:

```ini
; Required for Joomla HttpFactory to connect to local API
fsockopen
pfsockopen
```

Check with:
```bash
php -r "echo fsockopen('127.0.0.1', 7001) ? 'OK' : 'BLOCKED';"
```

Fix (LiteSpeed PHP 8.3):
```bash
vi /usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini
# Remove fsockopen, pfsockopen from disable_functions line
systemctl restart lsws
```

### 3.5 Network Requirements

| Port | Protocol | Direction | Purpose |
|------|---------|-----------|--------|
| `443` | HTTPS | Inbound | Joomla site |
| `7001` | HTTP | Internal only | Docker API |
| `9001-9100` | HTTPS | Inbound | Container SSL ports |
| `32768-60999` | TCP | Internal | Docker random host ports |

Open ports in UFW:
```bash
ufw allow 443/tcp
ufw allow 9001:9100/tcp
# Port 7001 is internal - do NOT open publicly
```

---

## 4. Pre-Installation Checklist

Complete these steps **before** installing the Joomla component:

### ✅ Step 1: Verify Docker is Running
```bash
docker ps
systemctl status docker
```
Expected: Docker engine running, `docker ps` shows output

### ✅ Step 2: Install Python FastAPI Service
```bash
# Create directory
mkdir -p /opt/dockerhost-api
cd /opt/dockerhost-api

# Create requirements.txt
cat > requirements.txt << 'EOF'
fastapi>=0.104.0
uvicorn[standard]>=0.24.0
docker>=6.1.0
python-dotenv>=1.0.0
pydantic>=2.0.0
EOF

# Install dependencies
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### ✅ Step 3: Deploy the API Service (`main.py`)
The file `/opt/dockerhost-api/main.py` must be present.

Verify:
```bash
python3 -c "import fastapi, docker, uvicorn; print('All deps OK')"
```

### ✅ Step 4: Create systemd Service
```bash
cat > /etc/systemd/system/dockerhost-api.service << 'EOF'
[Unit]
Description=DockerHost API Service
After=network.target docker.service
Requires=docker.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/dockerhost-api
ExecStart=/opt/dockerhost-api/venv/bin/uvicorn main:app --host 127.0.0.1 --port 7001
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable dockerhost-api
systemctl start dockerhost-api
```

### ✅ Step 5: Verify API is Running
```bash
curl -H "Authorization: Bearer dockerhost-secret-token-2026" \
  http://127.0.0.1:7001/health
```
Expected: `{"status": "ok", "docker": "connected"}`

### ✅ Step 6: Verify SSL Certificate
```bash
ls /etc/letsencrypt/live/
# Should show: ymmude.com or ymmude.com-0001
```

If not present, install:
```bash
apt install certbot python3-certbot-nginx
certbot certonly --nginx -d ymmude.com -d '*.ymmude.com'
```

### ✅ Step 7: Configure nginx SSL Include
```bash
# Create include directory if not exists
mkdir -p /etc/nginx/conf.d/

# Create SSL config file (API will populate this)
touch /etc/nginx/conf.d/dockerhost-ssl.conf

# Verify nginx includes this directory
grep -r 'conf.d' /etc/nginx/nginx.conf
# Should show: include /etc/nginx/conf.d/*.conf;
```

### ✅ Step 8: Verify PHP fsockopen
```bash
php8.3 -r "var_dump(function_exists('fsockopen'));"
# Expected: bool(true)
```

### ✅ Step 9: Open Firewall Ports
```bash
ufw allow 9001:9100/tcp
ufw reload
ufw status
```

### ✅ Step 10: Verify Joomla Database
```bash
mysql -u root -p -e "SHOW DATABASES LIKE 'jtest_db';"
# Should show jtest_db
```

---

## 5. Setup Walkthrough

### Step 1: Install the Joomla Component

1. Log in to Joomla Admin: `https://jtest.ymmude.com/administrator`
2. Navigate to: **Extensions → Install → Upload Package File**
3. Upload: `com_dockerhost.zip`
4. Click **Install**
5. Wait for success message: *"DockerHost installed successfully"*

### Step 2: Verify Component Settings

1. Go to: **Components → DockerHost → Settings**
2. Verify API URL: `http://127.0.0.1:7001`
3. Verify API Token: `dockerhost-secret-token-2026`
4. Click **Save**

### Step 3: Configure Joomla User Registration

1. Go to: **Users → Options**
2. Set **Allow User Registration** → `Yes`
3. Set **New User Registration Group** → `Registered`
4. Click **Save**

### Step 4: Create Frontend Menu Item (Optional)

1. Go to: **Menus → Main Menu → Add New Menu Item**
2. **Menu Item Type** → `DockerHost → Dashboard`
3. **Title**: `My Containers`
4. **Access**: `Registered` (logged-in users only)
5. Click **Save**

This creates a clean URL like `jtest.ymmude.com/my-containers`

### Step 5: Test Admin Dashboard

1. Go to: **Components → DockerHost**
2. You should see the Containers list
3. Verify **API Online** badge shows ✅ green
4. Existing containers should be listed

### Step 6: Test User Registration and Deploy

1. Open **incognito window** and go to `https://jtest.ymmude.com`
2. Register a new user account
3. After login, visit `https://jtest.ymmude.com/index.php?option=com_dockerhost`
4. Click **Deploy New Container**
5. Fill in:
   - **Name**: `test-nginx`
   - **Image**: `nginx:latest`
   - **Port**: `80`
6. Click **Deploy**
7. Container should appear with SSL URL: `https://ymmude.com:9001`

### Step 7: Verify SSL Access

```bash
# Test SSL port is accessible
curl -k -o /dev/null -w "%{http_code}" https://ymmude.com:9001
# Expected: 200
```

---

## 6. Component Features

### Admin Backend (`/administrator/index.php?option=com_dockerhost`)

| Feature | Description |
|---------|-------------|
| **Container Dashboard** | Lists ALL containers from all users with status, image, owner, SSL URL |
| **Deploy Form** | Deploy containers with name, image, port, env vars, restart policy |
| **Container Actions** | Start, Stop, Restart, Delete via AJAX (no page reload) |
| **Log Viewer** | Real-time container logs with dark terminal styling |
| **Image Management** | Pull images, list available images, delete unused |
| **Settings** | Configure API URL and authentication token |

### User Frontend (`/index.php?option=com_dockerhost`)

| Feature | Description |
|---------|-------------|
| **Guest View** | Login prompt for unauthenticated users with registration link |
| **My Containers** | Card-based grid showing only the logged-in user's containers |
| **Deploy Modal** | Bootstrap modal form for deploying new containers |
| **SSL URL Button** | Green "Open (SSL)" button opens container in new tab |
| **Direct Link** | Secondary link via direct host port |
| **Container Actions** | Start, Stop, Restart, Delete cards |

### SSL Provisioning System

| Feature | Description |
|---------|-------------|
| **Auto SSL Port** | Picks next available port from 9001-9100 range |
| **nginx Config** | Auto-writes server block to `dockerhost-ssl.conf` |
| **Firewall** | Auto-opens UFW port on container deploy |
| **WebSocket** | Full WebSocket proxy support (for apps like d3b4) |
| **ALLOWED_ORIGINS** | Auto-adds to container env for SPA/WebSocket apps |
| **Label Tracking** | Stores `ssl_port` label on container for persistence |

---

## 7. User Guide

### Registering an Account
1. Visit `https://jtest.ymmude.com`
2. Click **Register** in the site menu
3. Fill in name, username, email, password
4. Confirm email if verification is enabled
5. Log in with your credentials

### Accessing Your Dashboard
- URL: `https://jtest.ymmude.com/index.php?option=com_dockerhost`
- Or via menu item if configured: `https://jtest.ymmude.com/my-containers`

### Deploying a Container

1. Click **Deploy New Container**
2. Fill in the form:

| Field | Description | Example |
|-------|-------------|--------|
| **Container Name** | Unique name (lowercase, no spaces) | `my-nginx` |
| **Docker Image** | Public Docker Hub image | `nginx:latest` |
| **Container Port** | Port the app listens on inside container | `80` |
| **Restart Policy** | When container auto-restarts | `Unless Stopped` |
| **Environment Variables** | Key=value pairs for app config | `AUTH_LOGIN=user` |

3. Click **Deploy**
4. Wait ~10-30 seconds for image pull and container start
5. Your container card appears with **Open (SSL)** button

### Managing Your Containers

| Button | Action |
|--------|--------|
| ▶ **Start** | Start a stopped container |
| ⏹ **Stop** | Stop a running container (data preserved) |
| 🔄 **Restart** | Restart container (apply env changes) |
| 🗑 **Delete** | Permanently delete container and data |
| 🔒 **Open (SSL)** | Open app in new tab via HTTPS |
| 🔗 **Direct Link** | Open via direct IP:port (HTTP) |

---

## 8. Admin Guide

### Viewing All Containers
- Go to: **Admin → Components → DockerHost**
- See all containers from all users
- **Owner** column shows Joomla username
- **Access** column shows both SSL URL and direct URL

### Managing API Settings
- Go to: **Settings** tab in DockerHost
- **API URL**: `http://127.0.0.1:7001` (do not change unless API moved)
- **API Token**: Match token in `/opt/dockerhost-api/.env`

### Monitoring the API Service
```bash
# Check service status
systemctl status dockerhost-api

# View live logs
journalctl -u dockerhost-api -f

# Restart if needed
systemctl restart dockerhost-api
```

### Managing SSL Ports
```bash
# View all active SSL proxies
curl -H "Authorization: Bearer dockerhost-secret-token-2026" \
  http://127.0.0.1:7001/ssl/list

# Remove a specific SSL proxy
curl -X DELETE -H "Authorization: Bearer dockerhost-secret-token-2026" \
  http://127.0.0.1:7001/ssl/9001

# Check nginx config
cat /etc/nginx/conf.d/dockerhost-ssl.conf
```

### Updating the Component
1. Build new ZIP from `/a0/usr/workdir/com_dockerhost/`
2. Extensions → Install → Upload (Joomla handles upgrade automatically)

### Updating the API Service
```bash
# Edit API
vim /opt/dockerhost-api/main.py

# Syntax check
python3 -m py_compile /opt/dockerhost-api/main.py

# Restart
systemctl restart dockerhost-api
```

---

## 9. Troubleshooting

### ❌ "Invalid Security Token"
**Cause:** CSRF token mismatch  
**Fix:** Reinstall latest component ZIP. Ensure all AJAX fetch calls include `format=json` in URL.

### ❌ "Operation timed out after 30002 milliseconds"
**Cause:** PHP `fsockopen` is in `disable_functions`  
**Fix:**
```bash
# LiteSpeed PHP 8.3:
vi /usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini
# Remove fsockopen, pfsockopen from disable_functions
systemctl restart lsws
```

### ❌ Page Hangs / Never Loads
**Cause:** Too many stuck lsphp worker processes  
**Fix:**
```bash
killall lsphp
systemctl restart lsws
```

### ❌ API Error Badge in Dashboard
**Cause:** dockerhost-api service not running  
**Fix:**
```bash
systemctl restart dockerhost-api
curl http://127.0.0.1:7001/health
```

### ❌ Container Deployed but No SSL URL
**Cause:** SSL port allocation failed or nginx config error  
**Fix:**
```bash
nginx -t
cat /etc/nginx/conf.d/dockerhost-ssl.conf
systemctl reload nginx
```

### ❌ 502 Bad Gateway on SSL Port
**Cause:** Container stopped or wrong target port  
**Fix:**
```bash
docker ps -a | grep container-name
docker start container-name
```

### ❌ "Origin not allowed" (d3b4 / WebSocket apps)
**Cause:** Container missing `ALLOWED_ORIGINS` env var  
**Fix:** Delete and redeploy container — the API now auto-adds this env var. Or manually recreate:
```bash
docker stop my-container
docker rm my-container
docker run -d \
  --name my-container \
  -e ALLOWED_ORIGINS=https://ymmude.com:9001 \
  -p 8080 \
  piknar/d3b4:latest
```

### ❌ Black Screen on Container URL
**Cause:** App uses absolute paths incompatible with path-based proxy  
**Solution:** Use port-based SSL (current system) instead of path proxy. This is already the default.

---

## Quick Reference

```bash
# API service
systemctl status dockerhost-api
systemctl restart dockerhost-api
journalctl -u dockerhost-api -f

# API health check
curl -H "Authorization: Bearer dockerhost-secret-token-2026" http://127.0.0.1:7001/health

# List all containers via API
curl -H "Authorization: Bearer dockerhost-secret-token-2026" http://127.0.0.1:7001/containers

# List SSL proxies
curl -H "Authorization: Bearer dockerhost-secret-token-2026" http://127.0.0.1:7001/ssl/list

# nginx reload
nginx -t && systemctl reload nginx

# Docker containers
docker ps -a
docker logs <container-name> --tail 50

# Firewall
ufw status
ufw allow 9001:9100/tcp
```

---
*DockerHost v1.2.0 — Built on VPS 168.231.72.66 — jtest.ymmude.com*
