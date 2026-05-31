# Minidon Architecture & Design Principles

## Configuration Principles

### 1. All Server Settings in .env
All configuration variables that affect server behavior MUST be defined in the `.env` file.

**Required Variables:**
- `INSTANCE_NAME` - The name of your Minidon instance
- `TAGLINE` - The tagline displayed below the instance name
- `POSTS_PER_PAGE` - Number of posts to display per page

### 2. .env.example Must Contain All Variables
Every configuration variable in `.env` MUST also be present in `.env.example` with example values.

This ensures:
- Users know what variables are available
- New installations have a complete template to start from
- No hidden configuration options

### 3. No Fallback Values - Fail Fast
If a required environment variable is not set:
- **DO NOT** fall back to a default value
- **DO NOT** silently continue with reduced functionality
- **MUST** exit immediately with a clear error message

**Example of correct behavior:**
```php
if (getenv('INSTANCE_NAME') === false) {
    http_response_code(500);
    die("Missing required configuration: INSTANCE_NAME. Please add it to your .env file.");
}
```

**Example of INCORRECT behavior:**
```php
// DON'T DO THIS
$instanceName = getenv('INSTANCE_NAME') ?: 'Minidon';
```

## File Structure

```
minidon/
├── .env                # Required: Your configuration (NOT committed to git)
├── .env.example        # Required: Template with all config variables + examples
├── public/
│   └── index.php        # Entry point
├── src/
│   ├── Config.php
│   ├── Actor.php
│   ├── ActorRepository.php
│   └── Minidon.php
├── templates/
│   ├── base.xsl         # Common CSS and templates
│   ├── home.xsl         # Homepage template
│   ├── actor.xsl        # Single actor page template
│   └── post.xsl         # Single post template (backward compatibility)
└── data/
    ├── actors.csv       # Actor definitions
    └── @username/
        └── posts.csv    # Posts for each actor
```

## Code Organization Principles

### 1. XSLT Template Inheritance
- All templates `include` `base.xsl` for common CSS and templates
- CSS is defined once in `base.xsl` (no inline CSS duplication)
- Common components (header, etc.) are named templates in `base.xsl`

### 2. Single Responsibility
- Each PHP class has a single, well-defined responsibility
- Templates handle only presentation logic
- Business logic stays in PHP classes

### 3. Consistency
- All pages use the same header structure
- All actor cards use the same styling
- Navigation and UX patterns are consistent across all pages

## Data Files

### actors.csv Format
```csv
username,name,avatar,api_key
minidon,Minidon,https://api.dicebear.com/10.x/shapes/svg?seed=minidon,your_api_key_here
minidon_radio,Minidon Radio,https://api.dicebear.com/10.x/shapes/svg?seed=minidon_radio,your_api_key_here
```

Fields:
- `username` - Internal identifier (no @ prefix)
- `name` - Display name
- `avatar` - URL to avatar image (optional, falls back to first letter)
- `api_key` - API key for posting

### posts.csv Format
```csv
id,content,published,author
http://domain/@username/post1,Post content,2026-05-31T12:00:00+00:00,http://domain/@username
http://domain/@username/post2,Another post,2026-05-31T13:00:00+00:00,http://domain/@username
```

## URL Structure

| URL | Description |
|-----|-------------|
| `/` | Homepage - lists all actors |
| `/@username` | Actor profile with paginated posts |
| `/@username?page=N` | Actor profile, page N |
| `/@username/outbox` | ActivityPub outbox (JSON) |
| `/@username/inbox` | ActivityPub inbox (POST only) |

## Environment Setup

1. Copy `.env.example` to `.env`
2. Edit `.env` with your settings
3. Ensure all required variables are set
4. Start the server

**Never commit `.env` to version control!**
