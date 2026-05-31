<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    
    <xsl:include href="base.xsl"/>
    
    <xsl:template match="/">
        <html>
            <head>
                <title>Minidon - <xsl:value-of select="data/actorName"/></title>
                <xsl:call-template name="css"/>
            </head>
            <body>
                <div class="container">
                    <xsl:call-template name="header">
                        <xsl:with-param name="instanceName" select="data/instanceName"/>
                        <xsl:with-param name="tagline" select="data/tagline"/>
                    </xsl:call-template>
                    
                    <xsl:choose>
                        <xsl:when test="data/post">
                            <div class="post">
                                <div class="post-content"><xsl:value-of select="data/post/content"/></div>
                                <div class="post-meta">
                                    <xsl:value-of select="data/post/published"/>
                                </div>
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
