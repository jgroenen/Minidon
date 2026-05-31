<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:template match="/">
        <html>
            <head>
                <title>Minidon - Outbox</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 2rem; }
                    pre { background: #f4f4f4; padding: 1rem; border-radius: 4px; }
                </style>
            </head>
            <body>
                <h1><xsl:value-of select="data/actorName"/> - Outbox</h1>
                <pre><xsl:value-of select="data/posts"/></pre>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>