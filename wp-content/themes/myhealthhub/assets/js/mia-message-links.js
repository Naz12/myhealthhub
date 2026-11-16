/* Shared, safe link rendering for Mia's text and voice messages. */
(function () {
    'use strict';
    function escape(text) {
        return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function safeUrl(value) {
        try {
            var url = new URL(value, window.location.origin);
            return /^(https?:)$/.test(url.protocol) ? url : null;
        } catch (_) { return null; }
    }
    function labelFor(url) {
        var spanish = /^es\b/i.test(document.documentElement.lang || '') || (window.MiaHero && MiaHero.detectedLanguage === 'es');
        if (url.origin === window.location.origin) {
            if (/\/add-your-practice\/?$/i.test(url.pathname)) return spanish ? 'Agregar tu práctica' : 'Add Your Practice';
            if (/\/claim(?:-listing)?\/?$/i.test(url.pathname)) return spanish ? 'Reclamar perfil' : 'Claim This Listing';
            if (/\/(?:places|providers|gd_place)\//i.test(url.pathname)) return spanish ? 'Ver perfil' : 'View Profile';
            if (/\/contact\/?$/i.test(url.pathname)) return spanish ? 'Contactar' : 'Contact Us';
            return spanish ? 'Abrir página' : 'Open Page';
        }
        return (spanish ? 'Abrir ' : 'Open ') + url.hostname;
    }
    function inline(text) {
        var links = [], marker = 'MIALINK' + Math.random().toString(36).slice(2) + 'TOKEN';
        function stash(label, value) {
            var url = safeUrl(value);
            if (!url) return label;
            var anchor = document.createElement('a');
            anchor.className = 'mia-message-link';
            anchor.href = url.href;
            // Markdown emphasis belongs to formatting, not to the visible button label.
            anchor.textContent = (label || labelFor(url)).replace(/\\([*_])/g, '$1').replace(/^[*_]+|[*_]+$/g, '').trim();
            if (url.origin !== window.location.origin) {
                anchor.target = '_blank';
                anchor.rel = 'noopener noreferrer';
            }
            links.push(anchor.outerHTML);
            return marker + (links.length - 1) + 'END';
        }
        // Some model replies escape the Markdown delimiters: [label]\(url\).
        var source = String(text || '').replace(/\[([^\]\n]+)\]\\\((.*?)\\\)/g, '[$1]($2)');
        source = source.replace(/\[([^\]\n]+)\]\(((?:[^()\s]|\([^()\s]*\))+?)\)/g, function (_, label, url) {
            return stash(label, url);
        });
        // Mask links before formatting, so URLs are never linked twice or modified by emphasis.
        source = source.replace(/https?:\/\/[^\s<>"'\\]+|(^|[\s(])(\/(?!\/)[A-Za-z0-9][^\s<>"'\\]*)/g, function (match, prefix, path) {
            var value = path || match, suffix = '';
            while (/[.,;:!?\])]$/.test(value) && !(value.endsWith(')') && (value.match(/\(/g) || []).length >= (value.match(/\)/g) || []).length)) {
                suffix = value.slice(-1) + suffix;
                value = value.slice(0, -1);
            }
            var url = safeUrl(value);
            return (path ? prefix : '') + (url ? stash(labelFor(url), value) : escape(value)) + suffix;
        });
        var html = escape(source).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\*(.+?)\*/g, '<em>$1</em>');
        return html.replace(new RegExp(marker + '(\\d+)END', 'g'), function (_, index) { return links[Number(index)] || ''; });
    }
    window.MiaMessageLinks = { inline: inline, escape: escape };
})();
