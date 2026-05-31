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
                </style>
            </head>
            <body>
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
                            <div style="text-align: center;">
                                <a href="{data/actorUrl}" class="follow-button" title="Volg {data/actorName} op ActivityPub">Volg mij</a>
                            </div>
                        </xsl:when>
                        <xsl:otherwise>
                            <div class="empty-state">
                                <p>No post yet.</p>
                            </div>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
