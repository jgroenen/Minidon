<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:template match="/">
        <html>
            <head>
                <title>Minidon - Laatste Post</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 2rem; }
                    h1 { color: #333; }
                    small { color: #666; }
                </style>
            </head>
            <body>
                <h1><xsl:value-of select="data/actorName"/></h1>
                <xsl:choose>
                    <xsl:when test="data/post">
                        <p><xsl:value-of select="data/post/content"/></p>
                        <small><xsl:value-of select="data/post/published"/></small>
                    </xsl:when>
                    <xsl:otherwise>
                        <p>No post yet.</p>
                    </xsl:otherwise>
                </xsl:choose>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>