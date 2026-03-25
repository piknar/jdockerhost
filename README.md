# DockerHost

**Docker Container Hosting for Joomla 6**

[![Joomla](https://img.shields.io/badge/Joomla-6.0-blue.svg)](https://www.joomla.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net/)
[![Docker](https://img.shields.io/badge/Docker-24.0+-2496ED?logo=docker)](https://docker.com/)

A complete Docker container management system integrated with Joomla 6. Deploy, manage, and access containers through an elegant web interface with automatic SSL provisioning.

![Architecture](https://img.shields.io/badge/Architecture-MVC-orange)
![License](https://img.shields.io/badge/License-MIT-green.svg)

---

## ✨ Features

### 🔧 Admin Backend (Administrator)
- 📦 **Deploy Containers** - One-click deployment with custom images
- 🎛️ **Container Management** - Start, stop, restart, delete containers
- 🖼️ **Image Management** - Pull and manage Docker images
- 📊 **View Logs** - Real-time container logs
- ⚙️ **Settings** - Configure API endpoint and authentication

### 🌐 Port-Based SSL System
- 🔒 **Automatic SSL** - Containers accessible via HTTPS on dedicated ports (9001-9100)
- 🚀 **WebSocket Support** - Full support for real-time applications
- 📝 **No DNS Required** - Uses main domain SSL certificate

### 👥 Multi-User Support
- 🔐 **User Ownership** - Containers tagged with owner user ID
- 🔍 **Filtered Views** - Users see only their own containers

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        VPS / Server                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────┐    ┌─────────────────────────────┐   │
│  │  Joomla 6 (PHP)  │    │  DockerHost API Service     │   │
│  │  jtest.ymmude.com│───▶│  Python FastAPI             │   │
│  │                  │    │  http://127.0.0.1:7001      │   │
│  │  - Admin Backend │    │                             │   │
│  │  - Settings      │    │  - Container lifecycle      │   │
│  │  - Deploy Forms  │    │  - Image management         │   │
│  └──────────────────┘    │  - SSL port allocation      │   │
│                          └─────────────┬───────────────┘   │
│                                        │                    │
│                                        ▼                    │
│                          ┌─────────────────────────────┐   │
│                          │     Docker Engine           │   │
│                          │     /var/run/docker.sock    │   │
│                          └─────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              nginx SSL Port Proxies                   │   │
│  │  https://your-domain.com:9001 ──▶ container:8080     │   │
│  │  https://your-domain.com:9002 ──▶ container:XXXX     │   │
│  │  (ports 9001-9100 range)                             │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 📋 Requirements

### Server Requirements
- **Joomla 6.0+**
- **PHP 8.2+** with extensions: `sodium`, `intl`, `mbstring`, `curl`, `json`, `pdo_mysql`
- **fsockopen enabled** (must NOT be in `disable_functions`)
- **Docker Engine 24.0+**
- **nginx 1.18+**
- **Python 3.10+** with FastAPI

### Ports
- `443` - Main HTTPS
- `7001` - DockerHost API (internal)
- `9001-9100` - Container SSL ports (must be opened in firewall)

---

## 🚀 Installation

### Step 1: Install DockerHost API Service

```bash
# Install Python dependencies
pip install fastapi uvicorn docker python-dotenv

# Deploy API service
cp dockerhost-api/main.py /opt/dockerhost-api/
cp dockerhost-api/requirements.txt /opt/dockerhost-api/

# Create systemd service
cat > /etc/systemd/system/dockerhost-api.service << 'SERVICE'
[Unit]
Description=DockerHost API Service
After=docker.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/dockerhost-api
ExecStart=/usr/bin/python3 /opt/dockerhost-api/main.py
Restart=always
Environment="DOCKERHOST_TOKEN=your-secure-token"

[Install]
WantedBy=multi-user.target
SERVICE

systemctl enable --now dockerhost-api
```

### Step 2: Install Joomla Component

1. Download `com_dockerhost.zip` from [Releases](../../releases)
2. Go to **Joomla Administrator → Extensions → Install → Upload Package File**
3. Upload and install `com_dockerhost.zip`

### Step 3: Configure API Settings

1. Go to **Components → DockerHost → Settings**
2. Set API URL: `http://127.0.0.1:7001`
3. Set API Token: (same as DOCKERHOST_TOKEN above)

### Step 4: Configure nginx SSL

```bash
mkdir -p /etc/nginx/conf.d/dockerhost-includes/
```

Add to your main nginx config:
```nginx
include /etc/nginx/conf.d/dockerhost-includes/*.conf;
```

---

## 📝 Usage

### Deploy a Container

1. Go to **Components → DockerHost → Deploy**
2. Fill in:
   - **Container Name**: `my-app`
   - **Docker Image**: `nginx:latest`
   - **Container Port**: `80`
   - **Restart Policy**: `unless-stopped`
3. (Optional) Add environment variables
4. Click **Deploy**

### Access Container via SSL

After deployment, your container will be accessible at:
```
https://your-domain.com:9001
```

The port (9001-9100) is automatically assigned and configured with SSL.

### Manage Containers

Go to **Components → DockerHost → Containers** to:
- Start / Stop / Restart containers
- View container status and ports
- Delete containers
- View container logs

---

## 🔧 API Endpoints

The DockerHost API provides REST endpoints for container management:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | API health check |
| `/containers` | GET | List all containers |
| `/containers/deploy` | POST | Deploy new container |
| `/containers/{id}/start` | POST | Start container |
| `/containers/{id}/stop` | POST | Stop container |
| `/containers/{id}/restart` | POST | Restart container |
| `/images` | GET | List Docker images |
| `/images/pull` | POST | Pull new image |
| `/ssl/create` | POST | Create SSL port proxy |
| `/ssl/list` | GET | List SSL port mappings |

**Authentication:** Bearer token via `Authorization: Bearer <token>` header

---

## 📁 Project Structure

```
dockerhost/
├── joomla-component/          # Joomla MVC component
│   ├── admin/                 # Administrator backend
│   │   ├── src/
│   │   │   ├── Controller/    # Admin controllers
│   │   │   ├── Model/         # Admin models
│   │   │   ├── View/          # Admin views
│   │   │   ├── Helper/        # ApiHelper
│   │   │   └── Extension/     # Component extension
│   │   └── tmpl/              # Admin templates
│   ├── site/                  # Frontend (user dashboard)
│   │   └── src/
│   │       └── # Site views, controllers, models
│   └── com_dockerhost.xml     # Component manifest
│
├── dockerhost-api/            # Python FastAPI service
│   ├── main.py               # API server code
│   └── requirements.txt      # Python dependencies
│
└── docs/
    └── DOCKERHOST_DOCUMENTATION.md  # Full documentation
```

---

## 🐛 Troubleshooting

### CSRF Token Errors
Ensure `format=json` is included in all AJAX requests.

### 502 Bad Gateway
Check Docker API service is running:
```bash
curl http://127.0.0.1:7001/health -H "Authorization: Bearer <token>"
```

### fsockopen Disabled
Remove from `disable_functions` in php.ini:
```ini
; Remove fsockopen from this line:
; disable_functions = ...,fsockopen,...
```

### Containers Not Appearing in List
Check that `user_id` and `username` labels are being set during deployment.

---

## 🔒 Security Notes

- **API Token:** Keep your `DOCKERHOST_TOKEN` secure. Use a strong random string.
- **Firewall:** Only open ports 9001-9100 to the internet if containers need public access.
- **SSL:** The wildcard certificate for your main domain is used for all container SSL ports.
- **Permissions:** The API service runs as root to access Docker socket. Ensure the server is properly secured.

---

## 🤝 Contributing

Contributions welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Test on VPS before submitting PR
4. Follow the existing code style

---

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

---

## 🙏 Credits

Created by [Agent Zero](https://github.com/piknar) for Joomla 6 Docker container management.

---

**⭐ Star this repo if you find it useful!**
