# LlaravaLlama: Your Private AI Chat Suite

Welcome to LlaravaLlama - where Laravel and Ollama come together like peanut butter and chocolate to create the most versatile private chat suite available. Built for privacy enthusiasts and AI aficionados alike, LlaravaLlama brings enterprise-level features to your local machine.

## 📸 Preview

<div align="center">
  <img src="public/images/docs/preview.gif" alt="LlaravaLlama in action" width="800">
  <br />
  <em>LlaravaLlama in action - showcasing features and themes</em>
</div>

## ✨ Why LlaravaLlama?

LlaravaLlama combines the robust PHP framework Laravel with Ollama's powerful local AI models to create a completely private, self-hosted chat experience. From its eye-pleasing daylight theme to its soothing moonlight mode, every detail is crafted for your comfort and productivity.

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

## 🎮 Live Demo
[Try LlaravaLlama Now](https://llaravallama.com)

## 🛠 Technical Requirements

- PHP 8.1 or higher
- PHP-Curl
- Php-XML
- Composer
- Node.js & NPM
- Ollama installation
- Basic system for running LLMs (Most modern laptops will work!)

## 📱 Mobile Access Setup

1. Deploy LlaravaLlama on your:
    - Local PC
    - Cloud server (e.g., Linode)
2. Configure port forwarding
3. Connect to the ip address the machine running the app: `http://your_computer_ip_address:8000/`
4. Enjoy a premium mobile AI chat experience!

## 💾 Installation

```bash
# Clone the repository
git clone https://github.com/Better-Call-Jason/LlaraveLlama.git

# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Start the services
php artisan serve --host=0.0.0.0 --port=8000
npm run dev
ollama serve

#open port in firewall if needed
sudo ufw allow 8000
```

## 🔍 Debugging Features

LlaravaLlama comes with comprehensive debugging capabilities out of the box:

### Mobile Debug Mode
The app includes a full debug mode that works seamlessly on mobile devices. After completing the installation steps above, debugging is automatically available on your mobile device - no additional setup required.

[Previous sections remain the same until the Debugging Features section]

## 🔍 Debugging Features

LlaravaLlama comes with comprehensive debugging capabilities that can be easily enabled in your development environment:

### Enabling Debug Mode

Debug mode can be toggled in your `resources/views/app.blade.php`:

1. Set the debug panel flag:
```javascript
window.DEBUG_PANEL = true; // Enable debug mode
```

2. Enable the debug partial:
```php
@include('chat.partials.debug', ['debugEnabled' => true])
```

This will enable debugging features across all environments, including mobile devices. The debug panel provides detailed insights into:
- Application state
- Service operations
- API calls
- System interactions

When disabled (default production settings):
```javascript
window.DEBUG_PANEL = false;
```
```php
@include('chat.partials.debug', ['debugEnabled' => false])
```
This provides detailed insights into service operations, API calls, and system interactions.

## 👨‍💻 About the Author

Created by a passionate PHP/JS full-stack developer who believes in the democratization of AI technology. This project started as a personal tool, evolved through family use, and is now shared with the world. It represents a belief that powerful AI tools should be accessible to everyone while maintaining privacy and control over their data.

## 🌟 The Vision

LlaravaLlama was born from the amazing reality that today's LLM technology can run efficiently on consumer hardware. As these models become more powerful and accessible, tools like LlaravaLlama make it possible for everyone to harness their potential while maintaining complete privacy and control.

## 🤝 Contributing

Your contributions are welcome! Whether it's bug fixes, feature additions, or documentation improvements, feel free to submit a pull request.

## 📜 License

LlaravaLlama is open-source software licensed under the MIT license.
