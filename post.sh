#!/bin/bash

if [ -z "$1" ]; then
    echo "Gebruik: $0 \"Je bericht hier\""
    exit 1
fi

# Laad API key uit actors.csv (eerste regel na header, 4e kolom)
if [ -f "data/actors.csv" ]; then
    API_KEY=$(tail -n +2 data/actors.csv | head -n 1 | cut -d ',' -f4)
else
    echo "Fout: data/actors.csv bestand niet gevonden."
    exit 1
fi

# Post het bericht naar localhost:8080
curl -X POST \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"content\":\"$1\"}" \
  http://localhost:8080/post

echo ""