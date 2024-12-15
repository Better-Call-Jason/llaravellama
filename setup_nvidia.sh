#!/bin/bash

# Exit on any error
set -e

echo "Starting NVIDIA setup..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root"
    exit 1
fi

# Install NVIDIA drivers
echo "Installing NVIDIA drivers..."
apt-get update
apt-get install -y linux-headers-$(uname -r)
apt-get install -y nvidia-driver-535

# Setup the NVIDIA Container Toolkit repository
echo "Setting up NVIDIA repository..."
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey | gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg

curl -s -L https://nvidia.github.io/libnvidia-container/stable/deb/nvidia-container-toolkit.list | \
    sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' | \
    tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

# Update package listings
echo "Updating package listings..."
apt-get update

# Install the NVIDIA Container Toolkit
echo "Installing NVIDIA Container Toolkit..."
apt-get install -y nvidia-container-toolkit

# Configure Docker to use the NVIDIA runtime
echo "Configuring Docker runtime..."
nvidia-ctk runtime configure --runtime=docker

# Restart Docker daemon
echo "Restarting Docker daemon..."
systemctl restart docker

echo "Installation complete! The system needs to reboot to load the NVIDIA drivers."
echo "After reboot, run this verification command:"
echo "docker run --rm --gpus all nvidia/cuda:11.8.0-base-ubuntu22.04 nvidia-smi"

# Ask for reboot
read -p "Would you like to reboot now? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    reboot
fi