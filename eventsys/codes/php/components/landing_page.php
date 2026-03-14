<?php
include('../../includes/db.php');

// Fetch upcoming events (within next 60 days)
$events_query = "
    SELECT e.event_id, e.title, e.description, e.start_time, e.end_time,
           v.name AS venue_name, v.city,
           (e.capacity - COUNT(r.registration_id)) AS available_seats,
           e.capacity, e.price
    FROM event e
    LEFT JOIN venue v ON e.venue_id = v.venue_id
    LEFT JOIN registration r ON e.event_id = r.event_id
    WHERE e.start_time >= NOW()
    GROUP BY e.event_id
    ORDER BY e.start_time ASC
    LIMIT 6
";
$events_result = $conn->query($events_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#8B0000">
    <meta name="description" content="CCF Alabang — a vibrant community of faith, sports, worship, and purpose. Join us and grow together.">
    <title>CCF Alabang — Faith. Community. Purpose.</title>

    <link rel="manifest" href="../../manifest.json">
    <link rel="icon" type="image/png" href="../../assets/ccf-b1g-favicon.png">
    <link rel="apple-touch-icon" href="../../assets/ccf-b1g-favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../../css/landing_page.css">
</head>
<body>

<!-- ── NAVIGATION ── -->
<nav id="navbar">
    <a href="#home" class="nav-logo">
        <img src="../../assets/ccf-b1g-favicon.png" alt="CCF Alabang" class="nav-logo-img">
        <div class="nav-logo-text">
            <span class="nav-logo-main">CCF Alabang</span>
            <span class="nav-logo-sub">Eventix</span>
        </div>
    </a>

    <ul class="nav-links" id="navLinks">
        <li><a href="#home"       class="active-link">Home</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#ministries">Ministries</a></li>
        <li><a href="#events">Events</a></li>
        <li><a href="#contact">Contact</a></li>
    </ul>

    <a href="../auth/index.php" class="nav-cta">Join the Community</a>

    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>
</nav>

<div class="nav-overlay" id="navOverlay"></div>

<!-- ── HERO ── -->
<section class="hero" id="home">
    <div class="hero-bg-circle-1"></div>
    <div class="hero-bg-circle-2"></div>
    <div class="hero-pattern"></div>

    <div class="hero-content">
        <span class="hero-badge">
            <i data-lucide="heart" class="icon-sm"></i>
            CCF ALABANG
        </span>

        <h1 class="hero-title">
            Faith. <span class="highlight">Community.</span><br>
            <span class="highlight-gold">Purpose.</span>
        </h1>

        <p class="hero-subtitle">
            A vibrant, Christ-centered community in Alabang where worship, sports, 
            outreach, and fellowship come together — so every person can grow, belong, and make an impact.
        </p>

        <div class="hero-stats">
            <div class="stat-item">
                <span class="stat-number">500+</span>
                <span class="stat-label">Members</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">50+</span>
                <span class="stat-label">Events Yearly</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">10+</span>
                <span class="stat-label">Ministries</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">1</span>
                <span class="stat-label">Community</span>
            </div>
        </div>

        <div class="hero-buttons">
            <a href="#events" class="btn-primary">
                Browse Events
                <i data-lucide="zap" class="icon-md"></i>
            </a>
            <a href="#about" class="btn-secondary">
                Learn More
                <i data-lucide="arrow-right" class="icon-md"></i>
            </a>
        </div>
    </div>
</section>

<!-- ── ABOUT / PILLARS ── -->
<section class="about-section" id="about">
    <div class="section-header">
        <span class="section-tag">Who We Are</span>
        <h2 class="section-title">Built on <span class="highlight">Solid Ground</span></h2>
        <p class="section-subtitle">CCF Alabang is more than a church — it's a family rooted in faith, driven by love, and committed to making a lasting difference in every life we touch.</p>
    </div>

    <div class="pillars-grid">
        <div class="pillar-card">
            <div class="pillar-icon">
                <i data-lucide="book-open" class="icon-lg"></i>
            </div>
            <h3>Word-Centered</h3>
            <p>Everything we do is anchored in Scripture. We believe God's Word is alive, relevant, and transformative for every area of life.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon">
                <i data-lucide="users" class="icon-lg"></i>
            </div>
            <h3>Community-Driven</h3>
            <p>No one walks alone here. From small groups to large gatherings, we cultivate deep, authentic relationships that last a lifetime.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon">
                <i data-lucide="trophy" class="icon-lg"></i>
            </div>
            <h3>Excellence in All Things</h3>
            <p>Whether on the court, in worship, or in the workplace — we pursue excellence as an act of worship, giving our best to honor God.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon">
                <i data-lucide="globe" class="icon-lg"></i>
            </div>
            <h3>Outreach & Mission</h3>
            <p>Faith in action. We go beyond our walls through community service, charity events, and mission programs that transform lives.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon">
                <i data-lucide="music" class="icon-lg"></i>
            </div>
            <h3>Vibrant Worship</h3>
            <p>We gather with hearts full of gratitude. Our worship experiences are energetic, spirit-led, and centered on the presence of God.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon">
                <i data-lucide="trending-up" class="icon-lg"></i>
            </div>
            <h3>Growth & Discipleship</h3>
            <p>We invest in every stage of your journey — from first-timers to seasoned leaders — through mentorship, training, and life groups.</p>
        </div>
    </div>
</section>

<!-- ── MINISTRIES ── -->
<section class="ministries-section" id="ministries">
    <div class="section-header">
        <span class="section-tag">Get Involved</span>
        <h2 class="section-title">Find Your <span class="highlight">Ministry</span></h2>
        <p class="section-subtitle">There's a place for everyone at CCF Alabang. Discover the community that fits your passion, skill, and calling.</p>
    </div>

    <div class="ministries-grid">
        <div class="ministry-card">
            <div class="ministry-emoji">⚽</div>
            <div class="ministry-info">
                <h3>B1G Sports</h3>
                <p>Competitive sports leagues, tournaments, and training — where athletes glorify God through sportsmanship.</p>
            </div>
        </div>

        <div class="ministry-card">
            <div class="ministry-emoji">🎵</div>
            <div class="ministry-info">
                <h3>Worship Arts</h3>
                <p>Singers, musicians, and creatives using their gifts to lead the congregation in powerful worship experiences.</p>
            </div>
        </div>

        <div class="ministry-card">
            <div class="ministry-emoji">🤝</div>
            <div class="ministry-info">
                <h3>Outreach & Missions</h3>
                <p>Community feeding programs, outreach drives, and local mission trips that bring hope to those in need.</p>
            </div>
        </div>

        <div class="ministry-card">
            <div class="ministry-emoji">📚</div>
            <div class="ministry-info">
                <h3>Life Groups</h3>
                <p>Small, intimate gatherings for Bible study, prayer, and authentic community — the heart of CCF life.</p>
            </div>
        </div>

        <div class="ministry-card">
            <div class="ministry-emoji">🧒</div>
            <div class="ministry-info">
                <h3>Kids & Youth</h3>
                <p>Age-appropriate programs that plant seeds of faith and build strong, godly character from a young age.</p>
            </div>
        </div>

        <div class="ministry-card">
            <div class="ministry-emoji">🎤</div>
            <div class="ministry-info">
                <h3>Media & Tech</h3>
                <p>Behind-the-scenes creatives — video, photography, livestream, and digital teams that amplify the message.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── HIGHLIGHTS CAROUSEL ── -->
<section class="highlights-section" id="highlights">
    <div class="section-header">
        <span class="section-tag">Event Gallery</span>
        <h2 class="section-title">Moments That <span class="highlight">Matter</span></h2>
        <p class="section-subtitle">A glimpse into the energy, faith, and community of CCF Alabang events</p>
    </div>

    <div class="highlights-container">
        <div class="highlight-featured" id="featuredHighlight">
            <div class="highlight-image-wrapper">
                <img src="../../assets/highlights/soccer-sport.jpg" alt="Sports Fest" class="highlight-image">
            </div>
            <div class="highlight-info">
                <h3 class="highlight-title">Sports Fest</h3>
                <div class="highlight-meta">
                    <span class="highlight-meta-item">
                        <i data-lucide="calendar" class="icon-sm"></i>
                        July 27, 2025
                    </span>
                    <span class="highlight-meta-item">
                        <i data-lucide="users" class="icon-sm"></i>
                        85 Attendees
                    </span>
                </div>
            </div>
        </div>

        <div class="highlights-thumbs">
            <div class="highlight-thumb active" data-index="0">
                <img src="../../assets/highlights/soccer-sport.jpg" alt="Sports Fest">
                <div class="thumb-overlay"><span class="thumb-number">1</span></div>
            </div>
            <div class="highlight-thumb" data-index="1">
                <img src="../../assets/highlights/volleyball-sport.jpg" alt="Volleyball Championship">
                <div class="thumb-overlay"><span class="thumb-number">2</span></div>
            </div>
            <div class="highlight-thumb" data-index="2">
                <img src="../../assets/highlights/badminton-sport.jpg" alt="Badminton Tournament">
                <div class="thumb-overlay"><span class="thumb-number">3</span></div>
            </div>
            <div class="highlight-thumb" data-index="3">
                <img src="../../assets/highlights/pickleball-sport.jpg" alt="Pickleball Open">
                <div class="thumb-overlay"><span class="thumb-number">4</span></div>
            </div>
        </div>

        <div class="highlights-nav">
            <button class="nav-arrow" id="prevHighlight" aria-label="Previous">
                <i data-lucide="chevron-left" class="icon-md"></i>
            </button>
            <button class="nav-arrow" id="nextHighlight" aria-label="Next">
                <i data-lucide="chevron-right" class="icon-md"></i>
            </button>
        </div>
    </div>
</section>

<!-- ── EVENTS ── -->
<section class="events-section" id="events">
    <div class="section-header">
        <span class="section-tag">Get in the Game</span>
        <h2 class="section-title">Upcoming <span class="highlight">Events</span></h2>
        <p class="section-subtitle">Register now and be part of what God is doing in our community</p>
    </div>

    <div class="events-grid">
        <?php if ($events_result && $events_result->num_rows > 0): ?>
            <?php while ($event = $events_result->fetch_assoc()):
                $available   = max(0, (int)$event['available_seats']);
                $capacity    = (int)$event['capacity'];
                $pct         = $capacity > 0 ? ($available / $capacity) * 100 : 100;
                $isLow       = $pct < 30;
                $isFree      = $event['price'] == 0;
                $description = htmlspecialchars(mb_substr($event['description'], 0, 110)) . '…';
            ?>
            <div class="event-card">
                <div class="event-banner">
                    <i data-lucide="trophy" class="event-banner-icon"></i>
                </div>
                <div class="event-body">
                    <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>

                    <div class="event-meta">
                        <span class="event-meta-item">
                            <i data-lucide="calendar" class="icon-sm"></i>
                            <?= date('F j, Y · g:i A', strtotime($event['start_time'])) ?>
                        </span>
                        <?php if ($event['venue_name']): ?>
                        <span class="event-meta-item">
                            <i data-lucide="map-pin" class="icon-sm"></i>
                            <?= htmlspecialchars($event['venue_name']) ?><?= $event['city'] ? ', ' . htmlspecialchars($event['city']) : '' ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <p class="event-description"><?= $description ?></p>

                    <div class="event-badges">
                        <span class="event-badge <?= $isLow ? 'event-badge-low' : '' ?>">
                            <i data-lucide="users" class="icon-sm"></i>
                            <?= $available ?> / <?= $capacity ?> slots
                        </span>
                        <span class="event-badge <?= $isFree ? 'event-badge-free' : '' ?>">
                            <i data-lucide="tag" class="icon-sm"></i>
                            <?= $isFree ? 'FREE' : '₱' . number_format($event['price'], 2) ?>
                        </span>
                    </div>

                    <a href="../auth/index.php" class="event-register-btn">
                        Register Now
                        <i data-lucide="arrow-right" class="icon-md"></i>
                    </a>
                </div>
            </div>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="no-events">
                <i data-lucide="calendar-off" style="width:80px;height:80px;color:rgba(139,0,0,0.3);"></i>
                <h3 class="no-events-title">Something Big is Coming</h3>
                <p class="no-events-text">We're preparing amazing events for you. Check back soon or sign up to be the first to know!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── CTA SECTION ── -->
<section class="cta-section">
    <span class="cta-tag">Ready to Belong?</span>
    <h2 class="cta-title">Your Story Starts Here</h2>
    <p class="cta-subtitle">Whether you're new to faith or a seasoned believer — there's a seat at the table for you at CCF Alabang. Come as you are.</p>
    <div class="cta-buttons">
        <a href="../auth/index.php" class="btn-white">
            <i data-lucide="user-plus" class="icon-md"></i>
            Create an Account
        </a>
        <a href="#events" class="btn-outline-white">
            View Events
            <i data-lucide="arrow-right" class="icon-md"></i>
        </a>
    </div>
</section>

<!-- ── FOOTER ── -->
<footer id="contact">
    <div class="footer-grid">
        <div class="footer-section">
            <div class="footer-brand-logo">
                <img src="../../assets/ccf-b1g-favicon.png" alt="CCF Alabang">
                <span>CCF Alabang</span>
            </div>
            <p>Christ's Commission Fellowship — Alabang. A community committed to knowing Christ and making Him known through every avenue of life.</p>
            <div class="social-links">
                <a href="#" class="social-link" aria-label="Facebook"><i data-lucide="facebook" class="icon-sm"></i></a>
                <a href="#" class="social-link" aria-label="Instagram"><i data-lucide="instagram" class="icon-sm"></i></a>
                <a href="#" class="social-link" aria-label="YouTube"><i data-lucide="youtube" class="icon-sm"></i></a>
                <a href="#" class="social-link" aria-label="Email"><i data-lucide="mail" class="icon-sm"></i></a>
            </div>
        </div>

        <div class="footer-section">
            <h4>Quick Links</h4>
            <div class="footer-links">
                <a href="#home"><i data-lucide="home" class="icon-sm"></i> Home</a>
                <a href="#about"><i data-lucide="info" class="icon-sm"></i> About</a>
                <a href="#ministries"><i data-lucide="layers" class="icon-sm"></i> Ministries</a>
                <a href="#events"><i data-lucide="calendar" class="icon-sm"></i> Events</a>
                <a href="../auth/index.php"><i data-lucide="log-in" class="icon-sm"></i> Sign In</a>
            </div>
        </div>

        <div class="footer-section">
            <h4>Ministries</h4>
            <div class="footer-links">
                <a href="#ministries">⚽ B1G Sports</a>
                <a href="#ministries">🎵 Worship Arts</a>
                <a href="#ministries">🤝 Outreach</a>
                <a href="#ministries">📚 Life Groups</a>
                <a href="#ministries">🧒 Kids & Youth</a>
            </div>
        </div>

        <div class="footer-section">
            <h4>Get In Touch</h4>
            <div class="footer-contact-item">
                <i data-lucide="mail" class="icon-sm footer-contact-icon"></i>
                <span>alabang@ccf.ph</span>
            </div>
            <div class="footer-contact-item">
                <i data-lucide="phone" class="icon-sm footer-contact-icon"></i>
                <span>(02) 8772 3035</span>
            </div>
            <div class="footer-contact-item">
                <i data-lucide="map-pin" class="icon-sm footer-contact-icon"></i>
                <span>Madrigal Business Park<br>Muntinlupa, Metro Manila</span>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> CCF Alabang — Eventix. All rights reserved. Made with ❤️ for the community.</p>
    </div>
</footer>

<script>
lucide.createIcons();

// ── Mobile menu ──
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const navLinks      = document.getElementById('navLinks');
const navOverlay    = document.getElementById('navOverlay');
const navbar        = document.getElementById('navbar');

function openMenu()  { mobileMenuBtn.classList.add('active'); navLinks.classList.add('active'); navOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeMenu() { mobileMenuBtn.classList.remove('active'); navLinks.classList.remove('active'); navOverlay.classList.remove('active'); document.body.style.overflow = ''; }
function toggleMenu() { navLinks.classList.contains('active') ? closeMenu() : openMenu(); }

mobileMenuBtn.addEventListener('click', toggleMenu);
navOverlay.addEventListener('click', closeMenu);

document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', closeMenu);
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });

// ── Navbar scroll ──
window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.pageYOffset > 50);
});

// ── Smooth scroll ──
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (!target) return;
        e.preventDefault();
        window.scrollTo({ top: target.offsetTop - navbar.offsetHeight, behavior: 'smooth' });
    });
});

// ── Active nav link on scroll ──
const sections     = document.querySelectorAll('section[id]');
const allNavLinks  = document.querySelectorAll('.nav-links a');

function updateActiveLink() {
    let current = 'home';
    sections.forEach(sec => {
        if (window.pageYOffset + 160 >= sec.offsetTop) current = sec.id;
    });
    allNavLinks.forEach(link => {
        link.classList.toggle('active-link', link.getAttribute('href') === '#' + current);
    });
}

window.addEventListener('scroll', updateActiveLink);
updateActiveLink();

// ── Fade-in on scroll ──
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('fade-in-up'); });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.pillar-card, .ministry-card, .event-card').forEach(el => observer.observe(el));

// ── Highlights Carousel ──
const highlightsData = [
    { image: '../../assets/highlights/soccer-sport.jpg',     title: 'Sports Fest',              date: 'July 27, 2025',    attendees: 85 },
    { image: '../../assets/highlights/volleyball-sport.jpg', title: 'Volleyball Championship',   date: 'June 15, 2025',    attendees: 60 },
    { image: '../../assets/highlights/badminton-sport.jpg',  title: 'Badminton Open',            date: 'May 10, 2025',     attendees: 45 },
    { image: '../../assets/highlights/pickleball-sport.jpg', title: 'Pickleball Tournament',     date: 'April 20, 2025',   attendees: 38 }
];

let currentIdx = 0;

function goToHighlight(idx) {
    const featured = document.getElementById('featuredHighlight');
    const h        = highlightsData[idx];

    featured.style.opacity = '0';
    setTimeout(() => {
        featured.querySelector('.highlight-image').src        = h.image;
        featured.querySelector('.highlight-image').alt        = h.title;
        featured.querySelector('.highlight-title').textContent= h.title;
        featured.querySelectorAll('.highlight-meta-item')[0].innerHTML =
            `<i data-lucide="calendar" class="icon-sm"></i>${h.date}`;
        featured.querySelectorAll('.highlight-meta-item')[1].innerHTML =
            `<i data-lucide="users" class="icon-sm"></i>${h.attendees} Attendees`;
        lucide.createIcons();
        featured.style.opacity = '1';
    }, 280);

    document.querySelectorAll('.highlight-thumb').forEach((t, i) => {
        t.classList.toggle('active', i === idx);
    });

    currentIdx = idx;
}

document.querySelectorAll('.highlight-thumb').forEach(thumb => {
    thumb.addEventListener('click', () => goToHighlight(parseInt(thumb.dataset.index)));
});

document.getElementById('prevHighlight').addEventListener('click', () => {
    goToHighlight((currentIdx - 1 + highlightsData.length) % highlightsData.length);
});

document.getElementById('nextHighlight').addEventListener('click', () => {
    goToHighlight((currentIdx + 1) % highlightsData.length);
});

// Auto-play carousel
let autoplay = setInterval(() => goToHighlight((currentIdx + 1) % highlightsData.length), 5000);
const hlSection = document.querySelector('.highlights-section');
hlSection.addEventListener('mouseenter', () => clearInterval(autoplay));
hlSection.addEventListener('mouseleave', () => {
    autoplay = setInterval(() => goToHighlight((currentIdx + 1) % highlightsData.length), 5000);
});
</script>
</body>
</html>