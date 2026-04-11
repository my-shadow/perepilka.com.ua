# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A landing page for "Perepilka Oaza" — a Ukrainian quail farm selling eggs and live quails. Built with vanilla HTML/CSS/JavaScript, PHP backend for form submission and admin panel.

## Local Development

This project uses [DDEV](https://ddev.com/) for local development (Docker-based).

```bash
ddev start        # Start local environment
ddev stop         # Stop environment
ddev restart      # Restart services
ddev launch       # Open site in browser
```

The site runs at `https://perepilka.com.ua.ddev.site` when DDEV is active.

No build step — edit PHP/CSS/JS files directly. Changes are reflected immediately.

## Stack

- **Frontend**: Vanilla HTML5, CSS3, JavaScript with jQuery 3.7.1 (CDN)
- **Backend**: PHP 8.4 (`php/mail.php` — order form handler; `admin/index.php` — admin panel)
- **Database**: MariaDB 11.8 (configured but not currently used)
- **Web server**: Nginx + PHP-FPM
- **Icons/Fonts**: FontAwesome 6.5.1, Google Fonts (Inter, Playfair Display) — CDN

## Architecture

**Single PHP page** (`index.php`) with sections: Hero → Products → Advantages → About → Contact/Order Form → Footer. Reads `data/settings.json` at runtime to inject prices and meta tags.

**CSS** (`css/style.css`): Design token variables at the top (colors, fonts). Mobile-first responsive design, max container width 1200px.

**JavaScript** (`js/main.js`): jQuery for DOM, AJAX form submission, scroll-based animations, sticky header, hamburger menu, lightbox gallery, toast notifications. Product prices come from `window.PRICES` injected by PHP — do not hardcode prices in JS.

**PHP** (`php/mail.php`): Receives POST from order form, validates CSRF token, sanitizes inputs, sends email + Telegram notification, saves submission to `data/submissions.json`. Reads prices from `data/settings.json`.

**Admin** (`admin/index.php`): Password-protected panel (default password set via `ADMIN_PASSWORD` constant). Two tabs — Settings (prices + meta/SEO) and Submissions (with processed toggle). Default password: `admin`.

**Data** (`data/`):
- `settings.json` — product prices, meta title, description, phone, og_image, site_url. Protected from public access via `.ddev/nginx/deny-data.conf`.
- `submissions.json` — all order submissions, newest first. Written with `flock` for safe concurrent access.

**CSRF**: 32-byte token generated client-side via Web Crypto API, validated server-side.

## Key CSS Variables

```css
--primary: #C17F4E       /* warm brown */
--light-primary: #F5E6D8
--dark-text: #3D2E1F
--body-text: #6B5E50
--bg: #FAF7F2            /* cream */
--green-accent: #4A7C59
```
