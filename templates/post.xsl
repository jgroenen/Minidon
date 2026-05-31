<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:template match="/">
        <html>
            <head>
                <title>Minidon - <xsl:value-of select="data/actorName"/></title>
                <style>
                    :root {
                        --mastodon-primary: #3088d4;
                        --mastodon-secondary: #6364ff;
                        --mastodon-bg: #f6f8fa;
                        --mastodon-text: #191919;
                        --mastodon-text-light: #657786;
                        --mastodon-border: #e1e8ed;
                    }
                    
                    * {
                        box-sizing: border-box;
                        margin: 0;
                        padding: 0;
                    }
                    
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                        background-color: var(--mastodon-bg);
                        color: var(--mastodon-text);
                        line-height: 1.5;
                        min-height: 100vh;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        padding: 2rem 1rem;
                    }
                    
                    .container {
                        max-width: 600px;
                        width: 100%;
                    }
                    
                    h1 {
                        color: var(--mastodon-primary);
                        font-size: 1.5rem;
                        font-weight: 600;
                        margin-bottom: 1.5rem;
                        text-align: center;
                    }
                    
                    .post {
                        background: white;
                        border-radius: 12px;
                        border: 1px solid var(--mastodon-border);
                        padding: 1.25rem;
                        margin-bottom: 1rem;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    }
                    
                    .post-content {
                        font-size: 1.1rem;
                        margin-bottom: 0.75rem;
                        word-wrap: break-word;
                    }
                    
                    .post-meta {
                        color: var(--mastodon-text-light);
                        font-size: 0.9rem;
                    }
                    
                    .post-meta a {
                        color: var(--mastodon-primary);
                        text-decoration: none;
                    }
                    
                    .post-meta a:hover {
                        text-decoration: underline;
                    }
                    
                    .follow-button {
                        display: inline-block;
                        background-color: var(--mastodon-primary);
                        color: white;
                        border: none;
                        border-radius: 24px;
                        padding: 0.5rem 1.5rem;
                        font-size: 1rem;
                        font-weight: 500;
                        cursor: pointer;
                        margin-top: 1rem;
                        transition: background-color 0.2s ease;
                    }
                    
                    .follow-button:hover {
                        background-color: var(--mastodon-secondary);
                    }
                    
                    .follow-button:active {
                        transform: scale(0.98);
                    }
                    
                    .empty-state {
                        text-align: center;
                        color: var(--mastodon-text-light);
                        font-size: 1.1rem;
                    }
                    
                    /* Modal styles */
                    .modal-overlay {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background-color: rgba(0, 0, 0, 0.5);
                        z-index: 1000;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .modal-overlay.active {
                        display: flex;
                    }
                    
                    .modal {
                        background: white;
                        border-radius: 12px;
                        padding: 1.5rem;
                        width: 90%;
                        max-width: 400px;
                        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
                        animation: modalSlideIn 0.2s ease-out;
                    }
                    
                    @keyframes modalSlideIn {
                        from {
                            opacity: 0;
                            transform: translateY(-20px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                    
                    .modal h2 {
                        color: var(--mastodon-primary);
                        font-size: 1.25rem;
                        margin-bottom: 1rem;
                        font-weight: 600;
                    }
                    
                    .modal-form {
                        display: flex;
                        flex-direction: column;
                        gap: 1rem;
                    }
                    
                    .modal-form label {
                        font-weight: 500;
                        color: var(--mastodon-text);
                        font-size: 0.9rem;
                    }
                    
                    .modal-form input {
                        padding: 0.65rem;
                        border: 1px solid var(--mastodon-border);
                        border-radius: 6px;
                        font-size: 1rem;
                        font-family: inherit;
                        transition: border-color 0.2s ease;
                    }
                    
                    .modal-form input:focus {
                        outline: none;
                        border-color: var(--mastodon-primary);
                    }
                    
                    .modal-form input::placeholder {
                        color: var(--mastodon-text-light);
                    }
                    
                    .modal-buttons {
                        display: flex;
                        gap: 0.75rem;
                        justify-content: flex-end;
                        margin-top: 1.5rem;
                    }
                    
                    .modal-button {
                        padding: 0.5rem 1.25rem;
                        border: none;
                        border-radius: 6px;
                        font-size: 0.95rem;
                        font-weight: 500;
                        cursor: pointer;
                        transition: background-color 0.2s ease, opacity 0.2s ease;
                    }
                    
                    .modal-button.primary {
                        background-color: var(--mastodon-primary);
                        color: white;
                    }
                    
                    .modal-button.primary:hover {
                        background-color: var(--mastodon-secondary);
                    }
                    
                    .modal-button.secondary {
                        background-color: transparent;
                        color: var(--mastodon-text-light);
                    }
                    
                    .modal-button.secondary:hover {
                        background-color: #f0f0f0;
                        color: var(--mastodon-text);
                    }
                    
                    .actor-url-display {
                        background: #f6f8fa;
                        border: 1px solid var(--mastodon-border);
                        border-radius: 6px;
                        padding: 0.75rem;
                        font-size: 0.85rem;
                        word-break: break-all;
                        color: var(--mastodon-text-light);
                        margin-top: 0.5rem;
                    }
                </style>
            </head>
            <body>
                <!-- Follow Modal -->
                <div id="followModal" class="modal-overlay">
                    <div class="modal">
                        <h2>Volg op Mastodon</h2>
                        <p style="color: var(--mastodon-text-light); margin-bottom: 1rem; font-size: 0.95rem;">
                            Kopieer onderstaande URL en plak deze in het zoekveld van je Mastodon instance.
                        </p>
                        <div class="actor-url-display" id="actorUrlDisplay"></div>
                        <div class="modal-buttons">
                            <button type="button" class="modal-button secondary" onclick="closeFollowModal();">Sluiten</button>
                            <button type="button" class="modal-button primary" onclick="copyActorUrl();">Kopieer URL</button>
                        </div>
                    </div>
                </div>
                
                <div class="container">
                    <h1><xsl:value-of select="data/actorName"/></h1>
                    <xsl:choose>
                        <xsl:when test="data/post">
                            <div class="post">
                                <div class="post-content"><xsl:value-of select="data/post/content"/></div>
                                <div class="post-meta">
                                    <xsl:value-of select="data/post/published"/>
                                </div>
                            </div>
                            <div style="text-align: center; margin-top: 1rem;">
                                <button class="follow-button" onclick="openFollowModal();">Volg mij op Mastodon</button>
                            </div>
                        </xsl:when>
                        <xsl:otherwise>
                            <div class="empty-state">
                                <p>No post yet.</p>
                            </div>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>
                
                <script>
                    const actorUrl = '<xsl:value-of select="data/actorUrl"/>';
                    
                    function openFollowModal() {
                        document.getElementById('followModal').classList.add('active');
                        document.getElementById('actorUrlDisplay').textContent = actorUrl;
                    }
                    
                    function closeFollowModal() {
                        document.getElementById('followModal').classList.remove('active');
                    }
                    
                    function copyActorUrl() {
                        navigator.clipboard.writeText(actorUrl).then(function() {
                            // Show success feedback
                            const originalText = document.getElementById('actorUrlDisplay').textContent;
                            document.getElementById('actorUrlDisplay').textContent = 'URL gekopieerd! ✓';
                            setTimeout(function() {
                                document.getElementById('actorUrlDisplay').textContent = originalText;
                            }, 2000);
                        }).catch(function(err) {
                            // Fallback for older browsers
                            prompt('Kopieer deze URL:', actorUrl);
                        });
                        closeFollowModal();
                    }
                    
                    // Close modal when clicking outside
                    document.getElementById('followModal').addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeFollowModal();
                        }
                    });
                    
                    // Close modal with Escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            closeFollowModal();
                        }
                    });
                </script>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
