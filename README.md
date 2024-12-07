# LlaravaLlama: Your Private AI Chat Suite

Welcome to LlaravaLlama - where Laravel and Ollama come together like peanut butter and chocolate to create the most versatile private chat suite available. Built for privacy enthusiasts and AI aficionados alike, LlaravaLlama brings enterprise-level features to your local machine.

## 🎮 Live Demo
[Try LlaravaLlama Now](https://demo.llaravallama.com) <!-- Replace with actual demo URL -->

## 📸 Preview

<div align="center">
  <!-- Note: Most Markdown parsers allow basic HTML. This creates a simple image showcase -->
  <div class="carousel">
    <img src="public/images/docs/preview-light.png" alt="LlaravaLlama Light Theme" width="800"/>
    <img src="public/images/docs/preview-dark.png" alt="LlaravaLlama Dark Theme" width="800"/>
    <img src="public/images/docs/mobile-view.png" alt="LlaravaLlama Mobile View" width="800"/>
    <img src="public/images/docs/code-preview.png" alt="Code Rendering Example" width="800"/>
  </div>

<em>LlaravaLlama in action - showcasing both themes and mobile responsiveness</em>
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

## 🛠 Technical Requirements

- PHP 8.1 or higher
- Composer
- Node.js & NPM
- Ollama installation
- Basic system for running LLMs (Most modern laptops will work!)

## 📱 Mobile Access Setup

1. Deploy LlaravaLlama on your:
    - Local PC
    - Cloud server (e.g., Linode)
    - Home server
2. Configure port forwarding
3. Connect via IP address and port from your mobile device
4. Enjoy a premium mobile AI chat experience!

## 💾 Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/laravallama.git

# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Start the services
php artisan serve
npm run dev
ollama serve
```

## 👨‍💻 About the Author

Created by a passionate PHP/JS full-stack developer who believes in the democratization of AI technology. This project started as a personal tool, evolved through family use, and is now shared with the world. It represents a belief that powerful AI tools should be accessible to everyone while maintaining privacy and control over their data.

## 🌟 The Vision

LlaravaLlama was born from the amazing reality that today's LLM technology can run efficiently on consumer hardware. As these models become more powerful and accessible, tools like LlaravaLlama make it possible for everyone to harness their potential while maintaining complete privacy and control.

## 🤝 Contributing

Your contributions are welcome! Whether it's bug fixes, feature additions, or documentation improvements, feel free to submit a pull request.

## 📜 License

LlaravaLlama is open-source software licensed under the MIT license.
