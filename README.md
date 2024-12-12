# LlaraveLlama: Your Private AI Chat Suite

Welcome to LlaraveLlama - where Laravel and Ollama come together like peanut butter and chocolate to create the most versatile private chat suite available. Built for privacy enthusiasts and AI aficionados alike, LlaraveLlama brings enterprise-level features to your local machine.

## 📸 Preview

<div align="center">
    <img src="public/images/docs/preview.gif" alt="LlaraveLlama in action" width="800">
    <br />
   <em>LlaraveLlama in action - showcasing features, themes and debug mode</em>
   <br>
    <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License: MIT"></a>
</div>

## ✨ Why LlaraveLlama?

LlaraveLlama combines the robust PHP framework Laravel with Ollama's powerful local AI models to create a completely private, self-hosted chat experience. From its eye-pleasing daylight theme to its soothing moonlight mode, every detail is crafted for your comfort and productivity.

## 🚀 Key Features

- **100% Private**: Everything runs locally - your data never leaves your system
- **Mobile-Ready**: Responsive design that looks stunning on all devices
- **Beautiful Rendering**: Markdown and code blocks are rendered with syntax highlighting
- **Quick Copy**: One-click copying for any message or code block
- **Powerful Search**: Deep conversation search to find that important discussion from hundreds of chats ago
- **Custom Assistants**: Create and customize AI assistants for different tasks
- **Offline Capable**: Includes local CDN libraries - chat without internet once set up
- **JSON Storage**: Simple, efficient local storage for all your conversations
- **Theme Options**: Soft daylight and tender moonlight themes for comfortable viewing
- **Lightweight**: Runs smoothly on modest hardware - from cloud VPS to your old laptop
- **Full Debug Mode**: Out-of-the-box debugging support for mobile testing and development
- **Service Debugging**: Easily toggle comprehensive service-level debugging for troubleshooting
- **Model Health Recovery**: Automatic model health monitoring and recovery system that handles Ollama instabilities with automated repair and restart capabilities

## 🎮 Live Demo

[Try LlaraveLlama Now](https://llaravellama.com)

### Demo Environment Notes:
- Hosted on a basic Linode server - performance may vary with concurrent users
- All conversations are public and visible to other demo users
- Assistants and Conversations may be deleted at any time
- Perfect for a quick test drive before your local installation

The demo is intended as a preview of LlaraveLlama's features. For the best experience, we recommend running your own private instance.

## 🛠 System Requirements

### Supported Operating Systems
- Ubuntu 22.04 LTS (Tested and verified)
- Ubuntu 24.04 LTS (Tested and verified)

### Hardware Requirements
- Basic system capable of running LLMs (Most modern laptops will work!)
- Minimum 8GB RAM recommended
- 10GB free disk space

## 💾 Installation

LlaraveLlama now features a fully automated installation process that handles all dependencies and setup in under 3 minutes.

1. Clone the repository:
```bash
# clone the project
git clone https://github.com/Better-Call-Jason/LlaraveLlama.git

# nter the project directory
cd LlaraveLlama

```

2. Run the installation script:
```bash
#make the setup script executable
chmod +x setup.sh

#run the setup.sh script as sudo
sudo ./setup.sh
```

That's it! The script automatically:
- Installs all required system dependencies
- Sets up PHP, Node.js, and npm
- Installs and configures Ollama
- Downloads 3 lightweight AI models
- Configures the Laravel application
- Starts the server

Once complete, your LlaraveLlama instance will be available at:
- Local access: `http://localhost:8000`
- Network access: `http://your_computer_ip_address:8000`

### Future Starts

To start the server after initial installation:
```bash
sudo ./setup.sh --serve
```

## 📱 Mobile Access Setup

The application is automatically configured for mobile access during installation. Simply:

1. Ensure your device is on the same network as the host machine
2. Access LlaraveLlama using the network URL provided after installation
3. Enjoy a premium private mobile AI chat experience!

## 🔍 Debugging Features

LlaraveLlama includes comprehensive debugging capabilities that can be easily controlled through your environment settings.

### Enabling Debug Mode

Debug mode is controlled through your `.env` file:

```env
APP_DEBUG=true  # Enable debugging features
APP_DEBUG=false # Disable debugging features (production setting)
```

This setting automatically controls:
- The debug panel visibility
- Service operation logging
- System interaction details
- API call monitoring

No code changes are required - simply update your .env file and clear the configuration:
```bash
php artisan config:clear
php artisan cache:clear
```

### Mobile Debugging

The debug panel is fully responsive and works seamlessly on mobile devices when enabled through the .env file - no additional configuration required.

### Production Environments

This application has been fully tested on Linode servers. The demo runs on NGINX. The code is ready for a production server. Please open an issue for assistance with updating the Vite Config

## 👨‍💻 About the Author

Created by a passionate PHP/JS full-stack developer who believes in the democratization of AI technology. This project started as a personal tool, evolved through family use, and is now shared with the world. It represents a belief that powerful AI tools should be accessible to everyone while maintaining privacy and control over their data.

## 🌟 The Vision

LlaraveLlama was born from the amazing reality that today's LLM technology can run efficiently on consumer hardware. As these models become more powerful and accessible, tools like LlaraveLlama make it possible for everyone to harness their potential while maintaining complete privacy and control.

## 🤝 Contributing

Your contributions are welcome! Whether it's bug fixes, feature additions, or documentation improvements, feel free to submit a pull request.

## 📜 License

LlaraveLlama is open-source software licensed under the [MIT license](LICENSE). See the [LICENSE](LICENSE) file for the full license text.
