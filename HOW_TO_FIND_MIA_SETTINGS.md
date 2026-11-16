# How to Access Mia Settings in WordPress Admin

## 📍 Step-by-Step Guide

### 1. **Go to WordPress Admin Dashboard**
   - Navigate to: `http://localhost/myhealthhub/wp-admin/`
   - Login if prompted

### 2. **Find the Menu Item**
   Look in the **left sidebar** for:
   
   ```
   ❤️ My Health Hub
      ├── Dashboard
      ├── Leads (CPT)
      ├── Claims (CPT)
      ├── Testimonials (CPT)
      ├── Practice Submissions (CPT)
      └── Settings ← CLICK HERE
   ```

### 3. **Click on "Settings"**
   - This opens the Settings page with multiple tabs

### 4. **Look for the "Mia Chatbot" Tab**
   You should see tabs at the top:
   
   ```
   [General]  [Mia Chatbot]  [SMTP Email]  [Google Maps]
   ```
   
   Click on **"Mia Chatbot"**

### 5. **You Should See**
   - ✅ Enable Mia (toggle)
   - ✅ Grok API Key field
   - ✅ OpenAI API Key field
   - ✅ **Hive AI (DeepSeek) API Key field** ← Your provider
   - ✅ Mia Chat Provider (dropdown with Grok, OpenAI, DeepSeek)
   - ✅ Action Selection (dropdown)

---

## 🔍 Troubleshooting

### "I don't see 'My Health Hub' in the menu"
- **Solution:** Make sure the **myhealthhub-core plugin is activated**
  - Go to: Plugins
  - Find: "MyHealthHub Core"
  - Click: "Activate" (if not already active)

### "I see the menu but not the Settings submenu"
- **Solution:** Make sure you have **admin permissions**
- Try: Logout and login with the main admin account

### "Settings opens but no Mia tab"
- **Solution:** Clear WordPress cache
- If using cache plugin, clear it
- Try: Refresh the page (Ctrl+F5)

---

## 🎯 Quick Path to Mia Settings

```
Left Sidebar → My Health Hub → Settings → Mia Chatbot Tab
```

---

## ✅ When You're In The Right Place

You should see this heading:
```
🗨️ Mia Chatbot Settings
```

And these fields:
- Enable Mia (toggle)
- Grok API Key
- OpenAI API Key
- **Hive AI (DeepSeek) API Key** ← (This is new! Your provider)
- Mia Chat Provider (dropdown)

---

## 📸 What to Do There

1. **Select your provider** in the dropdown:
   - Choose: "DeepSeek (via Hive) — Fast, cost-efficient chat"

2. **Enter the Hive API Key** (you already have it):
   - Copy: `jl9uoqX1MT6CSkVpUMlmIA==`
   - Paste into: "Hive AI (DeepSeek) API Key" field

3. **Click "Test Connection"** button
   - Should show: ✅ Success! Hive DeepSeek API is working.

4. **Click "Save Settings"** at the bottom

5. **Done!** Mia will now use DeepSeek for chat responses

---

## 🆘 Still Can't Find It?

Try this direct URL:
```
http://localhost/myhealthhub/wp-admin/admin.php?page=myhh-settings&tab=mia
```

This should take you directly to the Mia Chatbot Settings tab.

