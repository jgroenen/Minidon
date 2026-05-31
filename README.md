# Minidon

**Minimale ActivityPub node voor automatische posts** (bijv. radiozenders).

---

## Gebruik

1. Configureer je actors in `data/actors.csv`:

```csv
username,name,api_key
Minidon Radio,Minidon Radio,je_api_key_hier
```

2. Voeg minidon.local toe aan je hosts bestand:

```bash
echo "127.0.0.1 minidon.local" | sudo tee -a /etc/hosts
```

3. Start de server:

```bash
php -S localhost:8080 public/index.php
```

4. Post een bericht:

```bash
curl -X POST \
-H "X-API-Key: je_api_key_hier" \
-H "Content-Type: application/json" \
-d '{"content":"Nu draait: Bohemian Rhapsody"}' \
http://localhost:8080/post
```

Of gebruik het `post.sh` script:

```bash
./post.sh "Nu draait: Bohemian Rhapsody"
```

5. Bekijk de post:

Open http://localhost:8080 in je browser.

## Meerdere Actors

Voeg meerdere regels toe aan `data/actors.csv`:

```csv
username,name,api_key
Minidon Radio,Minidon Radio,api_key_1
Another User,Another User,api_key_2
```