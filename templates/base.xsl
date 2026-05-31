<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    
    <!-- Common CSS link -->
    <xsl:template name="css">
        <link rel="stylesheet" type="text/css" href="/styles.css"/>
    </xsl:template>
    
    <!-- Header template -->
    <xsl:template name="header">
        <xsl:param name="instanceName"/>
        <xsl:param name="tagline"/>
        <header>
            <h1><a href="/"><xsl:value-of select="$instanceName"/></a> <span class="tagline"><xsl:value-of select="$tagline"/></span></h1>
        </header>
    </xsl:template>

</xsl:stylesheet>
