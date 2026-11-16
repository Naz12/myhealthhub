# Copilot Instructions for MyHealthHub WordPress Project

## 🎯 Core Principles
- **NO unnecessary documentation files** - Only create MD files if explicitly requested
- **Code-focused** - Prioritize working code over explanations
- **Minimal comments** - Only comment code that needs clarification
- **Respond in chat** - Explain decisions verbally, not in separate docs
- **Test-driven** - Verify changes work before committing

## 📂 Project Structure
```
myhealthhub/
├── wp-content/
│   ├── plugins/myhealthhub-core/    ← Main plugin code
│   │   ├── includes/                ← Classes (Mia, OpenAI, Admin, etc)
│   │   ├── assets/                  ← JS/CSS
│   │   └── myhealthhub-core.php     ← Plugin entry
│   └── themes/myhealthhub/          ← Theme (UI, styling)
├── .env                             ← API keys (NEVER commit)
├── .gitignore                       ← Protects .env
└── .git/                            ← Version control
```

## 🔑 API Providers
| Provider | Chat | Voice | Routing | Cost |
|----------|------|-------|---------|------|
| **OpenAI** | ✅ | ✅ | ✅ Decisions API | Higher |
| **Grok** | ✅ | ✅ | ✅ Classification | Medium |
| **DeepSeek** | ✅ | ❌ | ❌ | Lowest |

**Rules:**
- Chat: Use user's selected provider
- Routing (Actions): OpenAI or Grok only
- Voice (TTS): OpenAI only
- DeepSeek: Chat text only, never for routing/voice

## 🛠️ Code Changes - DO
- ✅ Make surgical, complete fixes
- ✅ Update related files together
- ✅ Test locally before pushing
- ✅ Use existing linters/tests
- ✅ Remove API keys before committing
- ✅ Add inline comments only when necessary
- ✅ Commit with descriptive messages

## 🛑 Code Changes - DON'T
- ❌ Create separate documentation files (explain in chat)
- ❌ Add unnecessary comments or docblocks
- ❌ Fix unrelated bugs (stay focused)
- ❌ Change coding style without reason
- ❌ Commit API keys or credentials
- ❌ Create testing utilities (use existing tools)

## 📝 Commit Messages
Format: `type: subject`

**Types:**
- `fix:` - Bug fixes
- `feat:` - New features
- `docs:` - Only critical documentation (setup, README)
- `refactor:` - Code cleanup
- `perf:` - Performance improvements

**Examples:**
```
✅ fix: Use correct Deploy.com endpoint for DeepSeek
✅ feat: Add Hive/DeepSeek integration
❌ docs: Explain why DeepSeek... (just say in chat)
❌ refactor: Cleanup code (too vague)
```

## 🗂️ Key Files - Don't Edit Unless Necessary
| File | Purpose | When to edit |
|------|---------|--------------|
| `class-mia-openai.php` | Provider routing, chat, voice | Adding providers, fixing bugs |
| `class-mia-decisions.php` | Action routing (OpenAI/Grok) | Only if changing action logic |
| `class-mia-chatbot.php` | Chat handler, intent engine | Bug fixes, feature additions |
| `class-admin.php` | Settings UI, tests | Adding settings, API tests |
| `.env` | Secrets (NEVER commit) | Local development only |

## 🧪 Testing Workflow
1. Make code changes
2. Test locally: `http://localhost/myhealthhub/`
3. Test in WordPress admin
4. Run existing tests if available
5. Commit only if working
6. Push to GitHub

## 💬 Communication Style
When making changes:
1. **Explain why** - What problem does it solve?
2. **Show what changed** - Brief code summary
3. **Tell result** - Did it work? Any issues?
4. **Ask for next steps** - What should we do?

**Example (GOOD):**
```
Found the issue: Hive API endpoint was wrong (thehive.ai instead of deploy.com).
Updated two files with correct endpoint.
Tested: Connection now works ✅

Ready to push?
```

**Example (BAD):**
```
[Creates 5 MD files explaining why endpoints matter]
[Commits 50 lines of documentation]
[Doesn't test anything]
```

## 🚫 What NOT to Create
❌ `WHY_*.md` - Explanatory files
❌ `GUIDE_*.md` - Tutorial files  
❌ `HOW_TO_*.md` - How-to guides
❌ `*.md` files without explicit request
❌ `IMPLEMENTATION_NOTES.md`
❌ `ARCHITECTURE_*.md`

If asked "explain how X works" → Answer in chat, don't create a file.

## ✅ What IS Okay to Create
✅ `.env.example` - Template for environment variables
✅ `README.md` - Project overview (if missing)
✅ `SETUP.md` - Installation instructions (if needed)
✅ Bug/fix documentation (if explicitly requested)
✅ Code with inline comments (minimal)

## 🔐 Security Rules
- ✅ Keep `.env` in `.gitignore`
- ✅ Use environment variables for all secrets
- ✅ Check `.env` file before committing
- ✅ Never paste API keys in messages
- ✅ Use `getenv()` to load credentials

## 📊 Git Workflow
```bash
# Create feature branch
git checkout -b feature/fix-name

# Make changes, test
# ...

# Commit with clear message
git commit -m "fix: brief description"

# Push to GitHub
git push origin feature/fix-name

# Create PR or merge
```

**Branch naming:**
- `feature/` - New features
- `fix/` - Bug fixes
- `refactor/` - Code cleanup
- `docs/` - Documentation

## 🎯 Focus Areas for This Project
1. **Mia Chatbot** - AI provider integration, routing, responses
2. **Provider Directory** - GeoDirectory integration, search
3. **UI/UX** - Theme styling, responsiveness, accessibility
4. **API Integration** - OpenAI, Grok, DeepSeek connections
5. **Admin Settings** - Configuration, testing tools

## ❓ Questions to Ask
Before creating files, ask yourself:
- "Is this explicitly requested?" (No → don't create)
- "Can I explain this in chat?" (Yes → don't create)
- "Does this belong in code comments?" (Yes → add to code)
- "Is this a setup/README?" (No → don't create)
- "Will this file be used/referenced?" (No → don't create)

---

**Summary:** Keep the codebase clean. Explain decisions in chat. Only create files when they're essential project artifacts, not documentation about the code.
