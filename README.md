# DockerHost for Joomla 6

[![Joomla 6](https://img.shields.io/badge/Joomla-6.1+-5091AD?style=flat&logo=joomla)](https://www.joomla.org/)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)](https://www.php.net/)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat&logo=docker)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-GPL--2.0+-green.svg)](LICENSE)

**Multi-user Docker container hosting integrated with Joomla 6.** Deploy, manage, and access containerized applications with automatic SSL provisioning.

## 🎯 Overview

DockerHost transforms Joomla into a container hosting platform. Users can deploy Docker containers directly from the admin interface, with each container automatically configured for SSL access on dedicated ports.

**Key Features:**
- 🔐 **Automatic SSL** - Containers get HTTPS via nginx on ports 9001-9100
- 👥 **Multi-user** - Containers tagged with user_id/username ownership
- 🚀 **One-click deploy** - Web interface for container lifecycle
- 📊 **Admin dashboard** - Full CRUD management
- 👁️ **Frontend view** - Read-only container status for users

## 📁 Project Structure

```
dockerhost-repo/
├── joomla-component/          # Joomla MVC Component
│   ├── admin/                 # Administrator backend
│   │   ├── src/Controller/    # Deploy, containers, images, logs controllers
│   │   ├── src/Model/         # Business logic
│   │   ├── src/View/          # HTML views
│   │   └── tmpl/              # Templates (deploy, containers, etc.)
│   ├── site/                  # Frontend (read-only)
│   │   ├── src/Controller/    # Dashboard, containers controllers
│   │   ├── src/Model/         # Dashboard model with API integration
│   │   └── tmpl/dashboard/    # Frontend template
│   └── com_dockerhost.xml     # Component manifest
├── dockerhost-api/            # Python FastAPI Service
│   ├── main.py                # API endpoints for Docker operations
│   ├── requirements.txt       # Python dependencies
│   └── .env                   # Configuration (API token, paths)
└── docs/
    └── DOCKERHOST_DOCUMENTATION.md  # Full technical documentation
```

## 🏗️ Architecture

```
┌─────────────────────────────────────────────┐
│  User                                      │
└──────────────┬──────────────────────────────┘
               │
        ┌──────▼──────┐
        │  Joomla 6   │◄── Admin Dashboard
        │  Component  │◄── Frontend View
        └──────┬──────┘
               │ HTTP API
        ┌──────▼──────┐
        │  Python     │ Port 7001
        │  FastAPI    │ Token-secured
        │  Service    │
        └──────┬──────┘
               │ Docker API
        ┌──────▼──────┐
        │   Docker    │
        │   Engine    │
        └──────┬──────┘
               │ Container ports
        ┌──────▼──────┐
        │    nginx    │ SSL Proxy
        │  (9001+)    │ Wildcard cert
        └─────────────┘
```

## ⚡ Quick Start

### Prerequisites

- Joomla 6.1+ with PHP 8.2+
- Docker Engine 20.10+
- Python 3.11+ with pip
- nginx with SSL wildcard certificate

### Installation

1. **Install the Joomla component:**
   ```bash
   cd joomla-component
   zip -r com_dockerhost.zip .
   # Upload via Joomla Administrator → Extensions → Install
   ```

2. **Install Python API service:**
   ```bash
   cd dockerhost-api
   pip install -r requirements.txt
   # Configure .env with API_TOKEN
   # Run: python main.py
   # Or use systemd service
   ```

3. **Configure nginx SSL proxy:**
   See docs/DOCKERHOST_DOCUMENTATION.md for complete nginx configuration.

### Usage

**Administrator:** https://yoursite.com/administrator/index.php?option=com_dockerhost

1. Click "Deploy" in the sidebar
2. Enter container name and Docker image
3. Optional: Set container port, restart policy, env vars
4. Click "Deploy Container"
5. Container appears in list with SSL URL

**Frontend:** https://yoursite.com/index.php?option=com_dockerhost

- View-only container status
- Click SSL "Open" button to access containers

## 🔧 Configuration

### API Service (.env)
```ini
API_TOKEN=your-secure-token
DOCKER_HOST=unix:///var/run/docker.sock
NGINX_CONF=/etc/nginx/sites-available/yoursite
SSL_CERT=/path/to/cert.pem
SSL_KEY=/path/to/key.pem
PORT_RANGE_START=9001
PORT_RANGE_END=9100
```

### Component Options
- Set API URL and token in Administrator → DockerHost → Options
- Configure user permissions for deploy access

## 📚 Documentation

- **[Full Documentation](docs/DOCKERHOST_DOCUMENTATION.md)** - Complete setup guide, API reference, troubleshooting
- **[Changelog](#changelog)** - Version history

## 🤝 Contributing

This project is GPL-2.0+ licensed. Contributions welcome!

## 📝 Changelog

### v1.2.0 (2026-03-26)
- ✅ Multi-user support with ownership labels
- ✅ Automatic SSL proxy creation on deploy
- ✅ Frontend ContainersController for AJAX actions
- ✅ Simplified read-only frontend view
- ✅ Fixed HTTP 500 errors (namespace, ApiHelper, menu items)
- ✅ Updated component author to ymmude

### v1.0.0 (2026-03-20)
- Initial release with admin dashboard
- Docker container management
- SSL proxy support

---

**Author:** ymmude  
**License:** GPL-2.0+  
**Repository:** https://github.com/piknar/jdockerhost
