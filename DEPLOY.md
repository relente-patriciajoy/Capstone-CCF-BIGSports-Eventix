# Eventix — Railway Deployment Guide

## Prerequisites
- Railway account at railway.app
- GitHub repository with your Eventix code

---

## Step 1: Push to GitHub
Make sure all your latest changes are pushed:
```bash
git add .
git commit -m "feat: prepare for Railway deployment"
git push origin main
```

---

## Step 2: Create Railway Project
1. Go to [railway.app](https://railway.app) and sign in
2. Click **New Project**
3. Select **Deploy from GitHub repo**
4. Choose your `Registration-System` repository

---

## Step 3: Add MySQL Database
1. In your Railway project, click **New**
2. Select **Database → Add MySQL**
3. Railway will automatically create a MySQL instance
4. Click on the MySQL service → **Variables** tab
5. Note these values: `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`, `MYSQLPORT`

---

## Step 4: Set Environment Variables
In your main service (PHP app) → **Variables** tab, add:

| Variable | Value |
|----------|-------|
| `DB_HOST` | Copy from `MYSQLHOST` |
| `DB_USER` | Copy from `MYSQLUSER` |
| `DB_PASS` | Copy from `MYSQLPASSWORD` |
| `DB_NAME` | Copy from `MYSQLDATABASE` |
| `DB_PORT` | Copy from `MYSQLPORT` |
| `MAIL_USER` | your Gmail address |
| `MAIL_PASS` | your Gmail App Password |

---

## Step 5: Import Database
1. In the MySQL service → **Connect** tab
2. Use the connection details to connect via TablePlus, DBeaver, or phpMyAdmin
3. Import your `event_registration.sql` file

Or use Railway's query panel to run the SQL directly.

---

## Step 6: Update PHPMailer Config
In your `notification_function.php` or wherever PHPMailer is configured, update to use environment variables:

```php
$mail->Host     = 'smtp.gmail.com';
$mail->Username = getenv('MAIL_USER') ?: 'eventix.system@gmail.com';
$mail->Password = getenv('MAIL_PASS') ?: 'your-local-app-password';
```

---

## Step 7: Deploy
Railway will auto-deploy when you push to GitHub.
Your app will be live at a URL like:
```
https://your-app-name.railway.app
```

---

## Notes
- The `backups/` and `qr_codes/` folders need write permissions — Railway's file system is ephemeral, so backups will be lost on redeploy. Consider using a cloud storage solution for production.
- Free tier gives $5/month credit which is enough for a demo
- Logs are available in Railway dashboard → **Deployments → View Logs**