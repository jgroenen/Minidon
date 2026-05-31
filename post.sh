#!/bin/bash

if [ -z "$1" ]; then
    echo "Gebruik: $0 \"Je bericht hier\""
    exit 1
fi

# Laad API key uit .env
if [ -f ".env" ]; then
    API_KEY=$(grep "MINIDON_API_KEY" .env | cut -d '=' -f2)
else
    echo "Fout: .env bestand niet gevonden."
    exit 1
fi

# Post het bericht naar localhost:8080
curl -X POST \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"content\":\"$1\"}" \
  http://localhost:8080/post

echo ""