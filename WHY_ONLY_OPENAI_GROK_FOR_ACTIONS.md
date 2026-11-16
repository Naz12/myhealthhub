# Why Only OpenAI & Grok Are Used for Actions (Decision Making)

## 🎯 Quick Answer

**"Actions" (decision making) require special APIs that only OpenAI and Grok provide. DeepSeek cannot do this.**

---

## 📊 What's the Difference?

### Regular Chat vs. Structured Decisions

| Feature | Chat API | Decisions API |
|---------|----------|---------------|
| **What it does** | Generate text responses | Make structured choices |
| **Input format** | Free-form text messages | Messages + choice options |
| **Output format** | Text string | Selected choice + confidence |
| **Use case** | Answering questions | Route to specific actions |
| **DeepSeek** | ✅ Supported | ❌ NOT supported |
| **OpenAI** | ✅ Supported | ✅ Supported (gpt-6-luna) |
| **Grok** | ✅ Supported | ✅ Supported |

---

## 🏗️ MyHealthHub Action Flow

### What Happens When User Sends Message

```
User: "I want to find a therapist in Seattle"
         ↓
    [Mia Chatbot]
         ↓
    ╔══════════════════════════════════════════════╗
    ║  Step 1: Determine What Action to Take       ║
    ║  Uses: OpenAI Decisions API or Grok          ║
    ╚══════════════════════════════════════════════╝
         ↓
    Actions to choose from:
    • provider_search      → Find new providers
    • provider_detail      → Show provider facts
    • provider_location    → Show address
    • provider_insurance   → Check insurance
    • provider_booking     → Show booking info
    • provider_compare     → Compare providers
    • platform_info        → About MyHealthHub
    • crisis               → Emergency response
    • general              → General conversation
    • clarify              → Need more info
         ↓
    [Decision Result: provider_search, 95% confidence]
         ↓
    ╔══════════════════════════════════════════════╗
    ║  Step 2: Execute the Action                  ║
    ║  Now use chat provider (can be any provider) ║
    ╚══════════════════════════════════════════════╝
         ↓
    Mia: "I'll help you find therapists in Seattle.
           What type of therapy are you interested in?"
```

---

## 🔑 Key Difference: Why Decisions Need Special APIs

### OpenAI Decisions API

```php
POST https://api.openai.com/v1/decisions

Request:
{
    "model": "gpt-6-luna",
    "input": "I want to find a therapist in Seattle",
    "questions": [{
        "name": "next_action",
        "type": "choice",
        "choices": [
            {"value": "provider_search", "description": "Find new providers..."},
            {"value": "provider_detail", "description": "Show facts..."},
            {"value": "general", "description": "General conversation..."}
        ]
    }]
}

Response:
{
    "answers": [{
        "name": "next_action",
        "type": "choice",
        "choice": "provider_search",  ← Selected action
        "confidence": 0.95             ← How confident (0-1)
    }]
}
```

### Regular Chat API (Any Provider)

```php
POST https://api.openai.com/v1/chat/completions
// OR
POST https://api.deploy.com/api/v1/chat/completions
// OR via Grok API

Request:
{
    "model": "gpt-4o",
    "messages": [{"role": "user", "content": "Hello"}]
}

Response:
{
    "choices": [{
        "message": {"content": "Hi there! How can I help?"}
    }]
}
```

**Key difference:** Decisions API forces the model to pick from a defined set of actions, while Chat API generates free-form text.

---

## 📋 Current Implementation

### File: `class-mia-decisions.php`

```php
public static function select_action($message, $state, $key = null) {
    // Hard-coded to use OpenAI only!
    $response = wp_remote_post('https://api.openai.com/v1/decisions', array(
        'headers' => array('Authorization' => 'Bearer ' . $key),
        'body' => wp_json_encode(array(
            'model' => 'gpt-6-luna',  // ← OpenAI exclusive model
            'input' => $message,
            'questions' => [
                'name' => 'next_action',
                'type' => 'choice',
                'choices' => [
                    ['value' => 'provider_search', ...],
                    ['value' => 'provider_detail', ...],
                    // ... 15 other actions
                ]
            ]
        ))
    ));
}
```

### Settings Code: `class-admin.php`

```php
// Only OpenAI and Grok options
<select name="myhh_mia_decision_provider">
    <option value="openai">OpenAI Decisions</option>
    <option value="grok">Grok Classification</option>
</select>
```

---

## ❌ Why DeepSeek Can't Be Used

### Reason 1: No Decisions API
- Deploy.com (DeepSeek provider) doesn't offer a Decisions API
- Only offers regular chat endpoint
- Can't force structured choice selection

### Reason 2: Model Limitation
- DeepSeek models are text-generation only
- Don't have built-in "choice selection" capability
- Would require manual prompting (unreliable)

### Reason 3: Architectural Mismatch
- MyHealthHub routing requires **guaranteed choice selection**
- If DeepSeek returns ambiguous text, routing breaks
- OpenAI Decisions API guarantees: "choice" + "confidence"

---

## 💡 How Grok Works (as an Alternative)

Grok doesn't have a "Decisions API" like OpenAI, but MyHealthHub **simulates it** using:

```php
// Grok classification workaround
$prompt = "User said: 'I want to find a therapist'

Possible actions:
1. provider_search
2. provider_detail
3. general
...

Which ONE action best matches? Reply with ONLY the number.";

// Parse response as a number → action
```

This works but is **less reliable** than OpenAI's native Decisions API.

---

## 🎯 Current Setup

### For Chat (User Responses)
Can use ANY provider:
- ✅ DeepSeek (cheapest)
- ✅ OpenAI (best quality)
- ✅ Grok (balanced)

```
Chat Provider Setting:
┌─────────────────────────┐
│ Select AI Chat Provider │
├─────────────────────────┤
│ ○ Grok (xAI)           │
│ ○ OpenAI               │
│ ○ DeepSeek (Deploy.com)│ ← NEW! Now works
└─────────────────────────┘
```

### For Actions (Routing)
Only specific providers:
- ✅ OpenAI (native Decisions API)
- ✅ Grok (classification workaround)
- ❌ DeepSeek (no API, no workaround)

```
Action Selection Setting:
┌──────────────────────────┐
│ Select Decision Provider │
├──────────────────────────┤
│ ○ OpenAI Decisions      │
│ ○ Grok Classification   │
└──────────────────────────┘

(No DeepSeek - not available)
```

---

## 🔄 User Experience Flow

### Scenario: User asks "Find me a therapist"

```
1. Message arrives at Mia
2. Action Decision (OpenAI/Grok ONLY):
   "This is a provider_search action"
3. Chat Response (Any provider):
   If OpenAI: "I'll help you find a therapist in your area..."
   If DeepSeek: "I'll help you find a therapist in your area..."
   If Grok: "I'll help you find a therapist in your area..."
4. All generate good responses, but routing happens first
```

---

## 📊 Cost Analysis

```
Two-Provider System: Optimal ✅

User Message
    ↓
[Action: OpenAI] ← Costs $0.001-0.005 per request
    ↓
[Chat: DeepSeek] ← Costs $0.00001-0.00005 per request
    ↓
Total: ~$0.005 per message (cheapest!)

vs.

All DeepSeek: Can't work ❌
- No decisions routing

All OpenAI: More expensive
- ~$0.001 per message (100x more)

Hybrid System: Best balance ✅
- Accurate routing (OpenAI)
- Cheap chat (DeepSeek)
```

---

## ✅ Why This Design Makes Sense

1. **Accuracy First**: Use reliable OpenAI Decisions for critical routing
2. **Cost Savings**: Use cheap DeepSeek for text generation
3. **No Risk**: Don't risk misrouting on uncertain DeepSeek choices
4. **Fallback**: If OpenAI fails, can still use Grok classification

---

## 🚀 Could We Add DeepSeek for Actions?

### Option 1: LLM-as-Judge Workaround
```php
$prompt = "Choose ONE: provider_search, provider_detail, etc.
User said: '$message'";

// Hope it picks correctly
$response = call_deepseek($prompt);
$action = extract_action($response);  // Fragile!
```
❌ **Problem**: Unreliable, can fail silently

### Option 2: Wait for Deploy.com Decisions API
- Would need Deploy.com to build equivalent to OpenAI's Decisions
- Unlikely soon
- Would take weeks

### Option 3: Use OpenAI for decisions, DeepSeek for chat (CURRENT)
✅ **Best approach** - Already implemented!

---

## 📝 Summary

| Task | Provider | Why |
|------|----------|-----|
| **Route action** | OpenAI/Grok | Needs Decisions API (forced choice) |
| **Generate chat** | DeepSeek/OpenAI/Grok | Any text model works |
| **Generate voice** | OpenAI/Grok | Only ones with TTS |

**DeepSeek can ONLY do:** Chat text generation
**DeepSeek CANNOT do:** Actions routing, voice/TTS

---

## 🎯 Recommendation

**Keep current setup:**
1. Actions: OpenAI (primary) or Grok (fallback)
2. Chat: Any provider (user chooses)
3. Voice: OpenAI (primary) or Grok (fallback)

**This gives you:**
- ✅ Accurate routing
- ✅ Cost efficiency
- ✅ High-quality voice
- ✅ User choice for chat provider
- ✅ Automatic fallbacks

**DO NOT** try to add DeepSeek to action selection - it won't work reliably.
