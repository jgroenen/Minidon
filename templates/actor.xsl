<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    
    <xsl:include href="base.xsl"/>
    
    <xsl:template match="/">
        <html>
            <head>
                <title><xsl:value-of select="data/actorName"/> - Minidon</title>
                <xsl:call-template name="css"/>
            </head>
            <body>
                <div class="container">
                    <xsl:call-template name="header">
                        <xsl:with-param name="instanceName" select="data/instanceName"/>
                        <xsl:with-param name="tagline" select="data/tagline"/>
                    </xsl:call-template>
                    
                    <div class="actors-grid">
                        <div class="actor-card">
                            <xsl:choose>
                                <xsl:when test="data/avatar and string-length(data/avatar) > 0">
                                    <img class="actor-avatar" src="{data/avatar}" alt="{data/actorName} avatar" />
                                </xsl:when>
                                <xsl:otherwise>
                                    <div class="actor-avatar">
                                        <xsl:value-of select="substring(data/actorName, 1, 1)"/>
                                    </div>
                                </xsl:otherwise>
                            </xsl:choose>
                            <div class="actor-info">
                                <div class="actor-name"><xsl:value-of select="data/actorName"/></div>
                                <a href="/@{data/username}" class="actor-username">@<xsl:value-of select="data/username"/></a>
                                
                                <xsl:choose>
                                    <xsl:when test="data/posts/item">
                                        <div class="actor-latest-post">
                                            <xsl:for-each select="data/posts/item">
                                                <div class="post">
                                                    <div class="post-content"><xsl:value-of select="content"/></div>
                                                    <div class="post-meta">
                                                        <xsl:value-of select="published"/>
                                                    </div>
                                                </div>
                                            </xsl:for-each>
                                        </div>
                                        
                                        <xsl:if test="data/pagination">
                                            <div class="pagination">
                                                <xsl:choose>
                                                    <xsl:when test="data/pagination/prevPage">
                                                        <a href="{data/pagination/prevPage}">← Vorige</a>
                                                    </xsl:when>
                                                    <xsl:otherwise>
                                                        <span>← Vorige</span>
                                                    </xsl:otherwise>
                                                </xsl:choose>
                                                
                                                <xsl:for-each select="data/pagination/pages/item">
                                                    <xsl:choose>
                                                        <xsl:when test="current = '1'">
                                                            <span class="current"><xsl:value-of select="number"/></span>
                                                        </xsl:when>
                                                        <xsl:otherwise>
                                                            <a href="{url}"><xsl:value-of select="number"/></a>
                                                        </xsl:otherwise>
                                                    </xsl:choose>
                                                </xsl:for-each>
                                                
                                                <xsl:choose>
                                                    <xsl:when test="data/pagination/nextPage">
                                                        <a href="{data/pagination/nextPage}">Volgende →</a>
                                                    </xsl:when>
                                                    <xsl:otherwise>
                                                        <span>Volgende →</span>
                                                    </xsl:otherwise>
                                                </xsl:choose>
                                            </div>
                                        </xsl:if>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <div class="no-posts">No posts yet</div>
                                    </xsl:otherwise>
                                </xsl:choose>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
        </html>
    </xsl:template>

</xsl:stylesheet>
