# Eventix — Render Deployment Guide

## Prerequisites
- Render account at render.com (sign up with GitHub)
- GitHub repository with your Eventix code

---

## Step 1: Push latest changes to GitHub
```bash
git add .
git commit -m "chore: prepare for Render deployment"
git push origin main
```

---

## Step 2: Create a MySQL Database on Render
1. Go to [render.com](https://render.com) and sign in with GitHub
2. Click **New +** → **MySQL** (or PostgreSQL — but stick with MySQL)
   > ⚠️ Note: Render's free tier uses **PostgreSQL** not MySQL. Since Eventix uses MySQL/MariaDB, you have two options:
   > - **Option A:** Use [PlanetScale](https://planetscale.com) for free MySQL (recommended)
   > - **Option B:** Pay $7/month for Render's MySQL instance
3. If using **PlanetScale** (free MySQL):
   - Go to planetscale.com → Sign up → New database
   - Create database named `event_registration`
   - Go to **Connect** → copy the connection string details
   - Import your SQL via their web console

---

## Step 3: Create a Web Service on Render
1. Click **New +** → **Web Service**
2. Connect your GitHub repo `Registration-System`
3. Fill in settings:
   - **Name:** `eventix`
   - **Region:** Choose closest to you (Singapore for PH)
   - **Branch:** `main`
   - **Runtime:** `PHP` — if not available, select **Docker** (see Step 4)
   - **Build Command:** leave empty
   - **Start Command:** `php -S 0.0.0.0:$PORT -t eventsys/codes/php`
4. Select **Free** tier
5. Click **Create Web Service**

---

## Step 4: If PHP runtime is not available — use Docker
Create a `Dockerfile` in your repo root:

```dockerfile
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev && \
    docker-php-ext-install gd

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

ENV APACHE_DOCUMENT_ROOT /var/www/html/eventsys/codes/php
RUN sed -i 's|/var/www/html|${APACHE_DOCUMENT_ROOT}|g' \
    /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

EXPOSE 80
```

Then in Render:
- **Runtime:** Docker
- **Dockerfile Path:** `./Dockerfile`

---

## Step 5: Set Environment Variables
In your Render web service → **Environment** tab, add:

| Key | Value |
|-----|-------|
| `DB_HOST` | your database host |
| `DB_USER` | your database user |
| `DB_PASS` | your database password |
| `DB_NAME` | `event_registration` |
| `DB_PORT` | `3306` |
| `MAIL_USER` | `eventix.system@gmail.com` |
| `MAIL_PASS` | `gjzo qozj stqh iomm` |

---

## Step 6: Import Database
**If using PlanetScale:**
1. Go to your PlanetScale database → **Console** tab
2. Paste and run each SQL chunk (chunk 1 through 7) in order

**If using Render MySQL:**
1. Go to your database → **Connect** tab
2. Use TablePlus or MySQL Workbench to connect
3. Import `event_registration.sql`

---

## Step 7: Deploy
- Render auto-deploys when you push to GitHub
- Your app will be live at:
```
https://eventix.onrender.com
```

---

## Notes
- Free tier on Render **sleeps after 15 minutes** of inactivity — first load after sleep takes ~30 seconds
- To avoid sleep: use [UptimeRobot](https://uptimerobot.com) (free) to ping your site every 5 minutes
- `qr_codes/` and `backups/` folders are ephemeral on Render — files are lost on redeploy
- Logs: Render dashboard → your service → **Logs** tab