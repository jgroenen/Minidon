# Minidon

**Minimale ActivityPub node voor automatische posts** (bijv. radiozenders).

---

## Gebruik

1. Maak een `.env` bestand:

```bash
echo "MINIDON_API_KEY=je_api_key" > .env
```

2. Voeg minidon.local toe aan je hosts bestand:

```bash
echo "127.0.0.1 minidon.local" | sudo tee -a /etc/hosts
```

2. Start de server:

```bash
php -S localhost:8080 index.php
```

3. Post een bericht:

```bash
curl -X POST \
-H "X-API-Key: je_api_key" \
-H "Content-Type: application/json" \
-d '{"content":"Nu draait: Bohemian Rhapsody"}' \
http://localhost:8080/post
```

4. Bekijk de post:

Open http://localhost:8080 in je browser.