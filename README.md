# MyHealthHub

A modern mental health provider directory platform for the Raleigh/Triangle area with an AI-powered chat assistant and voice support.

## Features

- **AI Chat Assistant (Mia)** - Smart routing to mental health providers with natural conversation
- **Voice Support** - Talk naturally with Mia using voice input/output (OpenAI & Grok)
- **Provider Directory** - Browse therapists, counselors, and wellness professionals
- **Smart Search** - Filter by specialty, location, and category
- **Responsive Design** - Mobile-first, gradient-heavy modern UI
- **Multiple AI Providers** - OpenAI, Grok, and DeepSeek/Hive API support

## Tech Stack

- **Platform**: WordPress 6.x
- **Theme**: Custom MyHealthHub theme
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Backend**: PHP 8.x, MySQL
- **AI APIs**: OpenAI, Grok, Hive/DeepSeek

## Quick Start

### Requirements
- PHP 8.0+
- MySQL 5.7+
- WordPress 6.0+
- Apache/XAMPP (for local development)

### Installation

1. **Clone/Extract the project**
   ```bash
   cd wp-content/themes/myhealthhub
   ```

2. **Configure AI providers** in WordPress Admin:
   - Settings → Mia Settings
   - Add your API keys (OpenAI, Grok, and/or Hive)

3. **Import sample data** (if provided):
   - Import provider CSV via WordPress admin

4. **Access the site**:
   ```
   http://localhost/myhealthhub
   ```

## Project Structure

```
wp-content/
├── themes/myhealthhub/
│   ├── assets/
│   │   ├── css/          # Stylesheets (hero, animations, redesign)
│   │   └── js/           # Chat, voice, and interactive scripts
│   ├── template-parts/   # Page sections (hero, providers, search, etc)
│   ├── functions.php     # Theme setup and API handling
│   ├── header.php        # Sticky gradient header
│   ├── footer.php        # Enhanced footer
│   └── front-page.php    # Homepage orchestrator
├── plugins/              # Chat & voice functionality
└── mu-plugins/           # Must-use plugins (if any)
```

## Key Files

- **Chat Logic**: `assets/js/mia-hero-chat.js`
- **Voice System**: `assets/js/mia-hero-voice.js`, `assets/js/mia-openai-live.js`
- **API Routes**: `wp-json/myhh/v1/chat`, `wp-json/myhh/v1/voice`
- **Styling**: `assets/css/mia-hero.css`, `assets/css/interactive-redesign.css`

## Recent Updates

✅ Fixed microphone icon visibility in voice button  
✅ Modernized UI with gradient designs across all pages  
✅ Merged browse sections into unified "Find Your Wellness" interface  
✅ Enhanced Popular Searches with interactive styling  
✅ Added scroll animations and improved responsiveness  

## Configuration

### AI Provider Selection

Edit in WordPress Admin → Mia Settings:

- **OpenAI**: GPT-4, text-to-speech (voice support)
- **Grok**: Fast responses, good for real-time (voice support)
- **DeepSeek/Hive**: Cost-efficient, strong reasoning

### Environment Variables

```
HIVE_API_KEY=your_key_here
OPENAI_API_KEY=your_key_here
GROK_API_KEY=your_key_here
```

## Support

For setup issues or feature requests, contact the development team or open an issue in this repository.

---

**Version**: 1.0.0  
**Last Updated**: November 16, 2024
