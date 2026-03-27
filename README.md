<div align="center">

[![Typing SVG](https://readme-typing-svg.demolab.com?font=Fira+Code&pause=800&color=00BFFF&width=600&lines=Welcome+to+Eventix!;📅+Your+All-in-One+Event+Registration+System)](https://git.io/typing-svg)

<br/>

<img width="100%" src="./eventsys/codes/assets/README.png" alt="Eventix Cover"/>

**ONE PLACE FOR EVERY PURPOSE**

![divider](https://capsule-render.vercel.app/api?type=waving&color=0:000428,100:004e92&height=80&section=footer)

---

</div>

## 📌 About the Project

**Eventix** is a **centralized event registration system** designed to seamlessly manage **sports and non-sports events** for all participants.
It replaces manual tracking with a **clean, user-friendly, and scalable digital solution**.

---

## ✨ Features

### 🔐 Authentication & Roles
- Multi-role system: **Admin**, **Event Head (Organizer)**, and **Participant**
- Session-based authentication with role protection on all pages
- **Forgot password flow** — OTP-based email verification and password reset
- **Account recovery** — participants can submit recovery requests reviewed by Admin

### 📋 Event Registration
- 🔗 **Centralized Registration** – Manage all events in one place
- 🏃 **Supports Sports & Non-Sports** – From tournaments to seminars
- 👥 **Inclusive Design** – Easy to use for all participants
- 📅 **Smart Scheduling** – Auto-assigns participants to tables upon registration
- 🔒 **Registration Guard** – Blocks registration for past/ended events automatically

### 🪑 Table Management & Seating Assignment *(New)*
- Event Head enables table management per event with optional **gender separation**
- Configure **number of tables** and **seats per table** from the Table Management page
- System auto-assigns participants using a **fill-first algorithm** — fills one table completely before opening the next
- **Gender routing** — male participants go to male tables, female to female tables automatically
- Event Head can **manually reassign** participants via a click-to-open modal per table
- **Unassigned participants** appear in a warning box for manual assignment
- **Live capacity calculator** — shows if table seats match event capacity
- Printable table layout for physical venue setup

### 🙋 Volunteer Management *(New)*
- Admin creates **volunteer events** with title, description, date, and location
- Define roles per event: **Ushering**, **Admin**, **Technical** — each with an assigned team lead
- System generates a **unique QR code** per volunteer event for sign-up
- Volunteers scan the QR code, log in if needed, and choose their role
- If not logged in — system saves the redirect and returns them after login
- Admin views all volunteers organized by role on the event detail page
- Participants can view their assignments in **My Volunteer Events** dashboard
- Volunteer link only appears in sidebar when the user has at least one sign-up

### 📊 Dashboards
- **Admin Dashboard** – Real-time insights on events, users, and participants
- **Event Head Hub** – Quick-action cards for managing events, reports, and announcements
- **Participant Dashboard** – Personal event overview with QR code access

### 📅 Event Management (Event Head)
- Create, edit, and delete events
- View **attendance reports**, **participant engagement**, and **inactive tracking**
- Send **email reminders** (3-day, 1-day, day-of) to registered participants
- Send **custom announcements** to all confirmed participants with history log
- View **announcement history** per event

### ✅ Attendance System
- **Check-in / Check-out** per event with time tracking
- **Auto-close missed checkouts** — if a participant checked in but never checked out before the event ended, the system automatically sets check-out time to the event end time and marks a note: *"Left without checking out"*
- **Attendance locked** for participants who never checked in to ended events (marked absent)
- Status cards: ✅ Complete, ⚠️ Left without checking out, 🔒 Absent/Locked

### 📆 Event Calendar
- Monthly calendar view with event pills per day
- Click any event to view details in a modal
- Previous / Next month navigation

### 🔍 Filter & Search
- **Filter dropdown** on Browse Events, My Events, and Attendance pages — filter by All / Upcoming / Past
- **Real-time search bar** — filters cards instantly by event name and venue (no page reload)
- **No-results state** with contextual empty message

### 📱 Responsive Design
- Fully responsive across all device sizes: large desktop, laptop (1024px), tablet, phone, and small phone (320px)
- Sidebar, banners, cards, tables, modals, and filter controls all adapt cleanly
- Hamburger menu on screens below 1280px for admin pages
- Admin sidebar dropdown (Maintenance) scrolls properly on mobile when expanded

### 🎫 QR Code System
- Each registration generates a unique QR code
- QR codes are **disabled for past events** — replaced with a locked placeholder and status badge
- Participants can view and download their QR code from My Events
- Volunteer events generate a separate shareable QR code for sign-up

### 📧 Email Notification System (PHPMailer + Gmail SMTP)
- Automated reminders sent at 3 days, 1 day, and day-of the event
- Custom announcement emails composed and sent by Event Heads
- Deduplication via `email_log` table — no duplicate reminders sent
- Announcements tracked separately in `announcement` table with full history

### 🛡️ Admin Panel
- **User Promotions** — change user roles and set custom permissions per user or per event
- **Backup & Restore** — create, download, and restore database backups with activity log
- **Recovery Requests** — review and respond to account recovery submissions
- **Volunteer Management** — create and manage volunteer events, roles, and team leads
- **All Events** — view and manage every event in the system
- **Attendance** — view attendance per event across all organizers

---

## 🧰 Tech Stack

### Backend
![PHP](https://img.shields.io/badge/PHP_8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

### Frontend
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

### Database
![MySQL](https://img.shields.io/badge/MariaDB-00758F?style=for-the-badge&logo=mysql&logoColor=white)

### Tools
![XAMPP](https://img.shields.io/badge/XAMPP-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)
![PhpMyAdmin](https://img.shields.io/badge/PhpMyAdmin-6C78AF?style=for-the-badge&logo=phpmyadmin&logoColor=white)
![PHPMailer](https://img.shields.io/badge/PHPMailer-336791?style=for-the-badge&logo=gmail&logoColor=white)
![phpqrcode](https://img.shields.io/badge/phpqrcode-333333?style=for-the-badge&logo=qrcode&logoColor=white)

---

## 🗂️ Database Tables

| Table | Purpose |
|-------|---------|
| `user` | Stores all users with role (admin, event_head, participant) |
| `role` | Role definitions and role-permission mappings |
| `permission` | System permission definitions |
| `user_permission` | Custom per-user permission overrides |
| `event` | Event details — title, venue, times, capacity, has_tables, gender_separated |
| `venue` | Venue names and addresses linked to events |
| `organizer` | Organizer profiles linked to event heads |
| `event_category` | Event categories (Sports / Non-Sports) |
| `event_table` | Table definitions per event — number, capacity, gender assignment |
| `registration` | Participant registrations per event with table number and status |
| `attendance` | Check-in/check-out times, status, and notes per registration |
| `volunteer_event` | Volunteer event details with unique QR token |
| `volunteer_role_type` | Role definitions per volunteer event (Ushering/Admin/Technical) |
| `volunteer_member` | Volunteer sign-ups per role with status |
| `otp_code` | OTP codes for registration, login, and password reset |
| `otp_log` | OTP usage history |
| `email_log` | Tracks sent reminder emails to prevent duplicates |
| `announcement` | Sent announcements per event with subject, message, and sender |
| `account_recovery_request` | Account recovery submissions reviewed by admin |
| `backup_log` | Admin database backup and restore activity log |
| `permission_audit_log` | Tracks permission changes made by admin |
| `event_access` | Per-event access grants for users with specific permissions |
| `trusted_device` | Trusted device tokens per user for login |

---

## 🗓️ Project Timeline

- **Project Timeline:** [Eventix - Project Timeline](https://www.notion.so/Project-Timeline-2ad3fa52b79c8170b4d3e9276c72eff6)

---

## 🏛️ Admin Access

The administrative interface can be accessed at:

```
localhost/Registration-System/eventsys/codes/php/admin/admin-login.php
```

---

## ⚡ Installation / Usage

1. Clone this repository:
   ```bash
   git clone "git@github.com:relente-patriciajoy/Registration-System.git"
   ```

2. Place the project folder inside `htdocs` (XAMPP):
   ```
   C:/xampp/htdocs/Registration-System/
   ```

3. Import the database:
   - Open **phpMyAdmin** → Create database `event_registration`
   - Import `event_registration.sql`

4. Start **Apache** and **MySQL** in XAMPP Control Panel

5. Open in your browser:
   ```
   http://localhost/Registration-System/eventsys/codes/php/
   ```

---

## 📁 Project Structure

```
Registration-System/
└── eventsys/
    └── codes/
        ├── assets/          # Images and logos
        ├── backups/         # Database backup files (.sql)
        ├── css/             # style.css, sidebar.css, calendar.css, event_head.css,
        │                    # management.css, auth.css, volunteer.css
        ├── includes/        # db.php, session.php, role_protection.php,
        │                    # qr_function.php, permission_functions.php,
        │                    # notification_function.php
        ├── js/              # calendar.js and other scripts
        ├── libraries/       # phpqrcode library
        ├── qr_codes/        # Generated QR code PNG files
        └── php/
            ├── admin/           # Admin dashboard, all events, attendance,
            │   │                # user promotions, backup & restore, recovery requests
            │   └── volunteer/   # Volunteer management (index, create, detail, serve_qr)
            ├── auth/            # Login, register, logout, forgot password,
            │                    # reset password, volunteer signup
            ├── calendar/        # calendar.php, get_events.php
            ├── components/      # sidebar.php, landing_page.php
            ├── dashboard/       # events.php, my_events.php, attendance.php,
            │                    # home.php, my_volunteer_events.php
            ├── event/           # event_register.php, manage_events.php,
            │                    # table_management.php, table_autoassign.php,
            │                    # get_table_participants.php, announcement.php,
            │                    # view_attendance.php, reports.php,
            │                    # participant_engagement.php, inactive_tracking.php
            └── qr/              # view_qr.php, generate_qr.php
```

---

## 👤 Developer

| Avatar | Name | Role |
|--------|------|------|
| <img src="https://github.com/relente-patriciajoy.png" width="70" style="border-radius:50%"> | **Patricia Joy Relente** | ![](https://img.shields.io/badge/Sole_Developer-800020?style=for-the-badge) |

> Designed, built, and maintained entirely by Patricia Joy Relente —
> from database schema and backend logic to frontend UI and responsive design.

---

<div align="center">

![divider](https://capsule-render.vercel.app/api?type=waving&color=0:000428,100:004e92&height=80&section=footer)

*Built with 💙 by Patricia Joy Relente*

</div>