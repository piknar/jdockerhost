# DockerHost v1.2.0 - Installation Guide

## Quick Install

### 1. Install Joomla Component
```bash
# Via Joomla Administrator:
Extensions → Install → Upload Package File
Select: com_dockerhost_v1.2.0.zip
```

### 2. Install Python API Service
```bash
cd /path/to/dockerhost-api
pip install -r requirements.txt

# Create .env file:
cat > .env << 'ENV'
API_TOKEN=your-secure-token-here
DOCKER_HOST=unix:///var/run/docker.sock
NGINX_CONF=/etc/nginx/sites-available/yoursite
SSL_CERT=/path/to/cert.pem
SSL_KEY=/path/to/key.pem
PORT_RANGE_START=9001
PORT_RANGE_END=9100
ENV

# Run the API:
python main.py
# Or use systemd service (see full docs)
```

### 3. Configure Joomla
- Go to Components → DockerHost → Options
- Set API URL: http://127.0.0.1:7001
- Set API Token (match .env file)
- Save

### 4. Test Deployment
- Go to Components → DockerHost → Deploy
- Deploy a test container (e.g., nginx:latest)
- Access via SSL URL shown

## Full Documentation
See DOCKERHOST_DOCUMENTATION.md for complete setup, troubleshooting, and API reference.
