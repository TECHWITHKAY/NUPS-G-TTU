# NUPS-G Church Website

A modern, responsive church website for NUPS-G TTU (National Union of Presbyterian Students of Ghana, Takoradi Technical University). Built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features

- **Homepage** - Welcome section, upcoming events, latest blogs, and quiz leaderboard
- **History** - Church history timeline and information
- **Executives** - List of church executives/leaders
- **Events** - Church events with dates and details
- **Blogs** - Blog posts with image/video support
- **Gallery** - Photo gallery
- **Quiz** - Interactive quiz for members with leaderboard
- **Donate** - Donation functionality
- **User Authentication** - Registration and login for members
- **Admin Dashboard** - Content management for admins

## Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 5.7+ / MariaDB 10.2+
- **Frontend**: HTML5, CSS3, JavaScript
- **Icons**: Font Awesome 6.4
- **Design**: Responsive, modern UI with gradients and animations
- **Containerization**: Docker (optional)

## Project Structure

```
├── admin/
│   └── dashboard.php       # Admin content management
├── favicon/                # Website favicons
├── images/                 # Static images (banners, logos)
├── uploads/                # User-uploaded files (images, videos, documents)
├── blog-detail.php         # Single blog post page
├── blogs.php               # Blog listing page
├── config.php              # Configuration and constants
├── database.sql            # Database schema (MySQL)
├── db.php                  # Database connection and helpers
├── donate.php              # Donation page
├── events.php              # Events listing
├── executives.php          # Church executives
├── footer.php              # Site footer
├── gallery.php             # Photo gallery
├── header.php              # Site header (navigation)
├── history.php             # Church history
├── index.php               # Homepage
├── login.php               # User login
├── logout.php              # User logout
├── nupsglogo.png           # Church logo
├── profile.php             # User profile
├── quiz.php                # Quiz page
├── register.php            # User registration
├── Dockerfile              # Docker container definition
├── Buildfile               # Build verification script
├── .env.example            # Environment variables template
└── README.md               # This file
```

## Installation

### Prerequisites

- Web server (Apache/Nginx/XAMPP/WAMP)
- PHP 8.0 or higher with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.2+
- Composer (optional, if adding dependencies later)

### Option A: Docker Setup (Recommended)

1. **Clone/Download** - Copy all files to your project directory

2. **Build and run**:
   ```bash
   docker build -t nupsg-website .
   docker run -p 8080:80 nupsg-website
   ```

3. **Access the site** at `http://localhost:8080`

### Option B: Manual Setup

1. **Clone/Download** - Copy all files to your web server directory

2. **Configure Database**:
    - Create a MySQL database named `nupsg_church`
    - Import the `database.sql` file to create tables and seed default data

3. **Configure Environment**:
    - Copy `.env.example` to `.env`
    - Update database credentials and site URL in `.env` or directly in `config.php`

    ```env
    DB_HOST=localhost
    DB_USER=root
    DB_PASS=your_password_here
    DB_NAME=nupsg_church
    SITE_NAME=NUPS-G Church
    SITE_URL=http://localhost
    ADMIN_EMAIL=admin@nupsgchurch.com
    ```

4. **Set Permissions**:
    - Make `uploads/` folder writable (for image/video uploads)
    ```bash
    chmod -R 755 uploads/
    ```

5. **Access the Site**:
    - Open your browser and navigate to the project URL

## Default Credentials

| Role  | Email                      | Password |
|-------|----------------------------|----------|
| Admin | admin@nupsgchurch.com      | admin123 |

> **Note**: Change the default admin password immediately after first login.

## Configuration

### Site Settings (config.php / .env)

| Constant | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | Database host | `localhost` |
| `DB_USER` | Database username | `root` |
| `DB_PASS` | Database password | `` |
| `DB_NAME` | Database name | `nupsg_church` |
| `SITE_NAME` | Website name | `NUPS-G Church` |
| `SITE_URL` | Website URL | `http://localhost` |
| `ADMIN_EMAIL` | Admin contact email | `admin@nupsgchurch.com` |

### Image Requirements

- **Logo**: `nupsglogo.png` (recommended 200x200px)
- **Banner**: `images/banner.jpg` (recommended 1920x1080px)
- **History Banner**: `images/history.jpg` (recommended 1920x1080px)

## Usage

### For Visitors
- Browse church information, events, and blogs
- View photo gallery
- Take the quiz

### For Members
- Register and login
- Take quizzes and compete on leaderboard
- Update profile information
- View personalized content

### For Admins
- Access `/admin/dashboard.php`
- Manage executives (add/delete with contact info and photos)
- Manage events (add/delete with images)
- Manage blog posts (add/edit/delete with images or videos)
- Upload media to gallery
- Manage quiz questions and schedule
- View donation records
- View site statistics

## Supported Media

### Blog Images
- JPG, JPEG, PNG, GIF, WebP

### Blog Videos
- MP4, WebM, MOV, AVI, MKV

### Gallery Media
- Images: JPG, JPEG, PNG, GIF, WebP
- Documents: PDF, DOC, DOCX

## Responsive Design

The website is fully responsive and works on:
- Desktop computers
- Laptops
- Tablets
- Mobile phones

Media queries are implemented for:
- 1200px (Large desktops)
- 992px (Desktops)
- 768px (Tablets)
- 576px (Large mobile phones)
- 480px (Small mobile phones)

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Security Features

- Password hashing (bcrypt)
- Session management
- SQL injection prevention (prepared statements)
- XSS protection (output sanitization with `htmlspecialchars`)
- CSRF token protection on admin forms
- File upload validation (type, size, and path checks)

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `.env` or `config.php`
   - Ensure MySQL server is running
   - Check that `database.sql` has been imported

2. **Upload Errors**
   - Check folder permissions on `uploads/`
   - Check PHP `upload_max_filesize` setting
   - Ensure the `uploads/` directory exists

3. **Session Issues**
   - Ensure PHP session is working
   - Check browser cookie settings
   - Verify `session_start()` is called before any output

4. **Docker Issues**
   - Ensure port 80/8080 is not already in use
   - Check Docker logs: `docker logs <container_id>`

## License

This project is for educational/church use.

## Credits

- Font Awesome for icons
- NUPS-G TTU community
