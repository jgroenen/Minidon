<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    
    <xsl:include href="base.xsl"/>
    
    <xsl:template match="/">
        <html>
            <head>
                <title>Minidon - Fediverse Server</title>
                <xsl:call-template name="css"/>
            </head>
            <body>
                <div class="container">
                    <xsl:call-template name="header">
                        <xsl:with-param name="instanceName" select="data/instanceName"/>
                        <xsl:with-param name="tagline" select="data/tagline"/>
                    </xsl:call-template>
                    
                    <div class="actors-grid">
                        <xsl:choose>
                            <xsl:when test="data/actors/item">
                                <xsl:for-each select="data/actors/item">
                                    <div class="actor-card">
                                        <xsl:choose>
                                            <xsl:when test="avatar and string-length(avatar) > 0">
                                                <img class="actor-avatar" src="{avatar}" alt="{name} avatar" />
                                            </xsl:when>
                                            <xsl:otherwise>
                                                <div class="actor-avatar">
                                                    <xsl:value-of select="substring(name, 1, 1)"/>
                                                </div>
                                            </xsl:otherwise>
                                        </xsl:choose>
                                        <div class="actor-info">
                                            <div class="actor-name"><xsl:value-of select="name"/></div>
                                            <a href="{url}" class="actor-username">@<xsl:value-of select="username"/></a>
                                            
                                            <xsl:choose>
                                                <xsl:when test="lastPost">
                                                    <div class="actor-latest-post">
                                                        <div class="post-content"><xsl:value-of select="lastPost/content"/></div>
                                                        <div class="post-meta">
                                                            <xsl:value-of select="lastPost/published"/>
                                                        </div>
                                                    </div>
                                                </xsl:when>
                                                <xsl:otherwise>
                                                    <div class="no-posts">No posts yet</div>
                                                </xsl:otherwise>
                                            </xsl:choose>
                                        </div>
                                    </div>
                                </xsl:for-each>
                            </xsl:when>
                            <xsl:otherwise>
                                <div class="empty-state">
                                    <h2>No actors configured</h2>
                                    <p>Add actors to your Minidon instance to start posting.</p>
                                </div>
                            </xsl:otherwise>
                        </xsl:choose>
                    </div>
                </div>
            </body>
        </html>
    </xsl:template>

</xsl:stylesheet>
