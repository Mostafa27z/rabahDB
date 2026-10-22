#!/usr/bin/env bash
set -euo pipefail

# -------------------------------------------------
# Install Docker Engine and Docker Compose (latest stable)
# -------------------------------------------------

# Install required utilities
yum install -y yum-utils device-mapper-persistent-data lvm2

# Add Docker repository
yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

# Install Docker packages
yum install -y docker-ce docker-ce-cli containerd.io

# Enable and start Docker service
systemctl enable --now docker

# Install Docker Compose (v2.27.0 at time of writing)
curl -L "https://github.com/docker/compose/releases/download/v2.27.0/docker-compose-linux-x86_64" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# -------------------------------------------------
# Install Nginx (reverse‑proxy)
# -------------------------------------------------

yum install -y nginx
systemctl enable --now nginx

# -------------------------------------------------
# Install Certbot for Let’s Encrypt certificates
# -------------------------------------------------

yum install -y epel-release
yum install -y certbot python3-certbot-nginx

# -------------------------------------------------
# Open firewall ports (HTTP, HTTPS)
# -------------------------------------------------

firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload

echo "✅ Server bootstrap completed. You can now clone the repo, copy the Dockerfiles, and run 'docker compose up -d'."
