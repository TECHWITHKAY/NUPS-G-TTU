@echo off
echo ==========================================
echo   NUPS-G TTU Church Website - Docker Setup
echo ==========================================
echo.

echo [1/3] Checking Docker installation...
docker --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker is not installed or not in PATH.
    echo Please install Docker Desktop from https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker Compose is not installed.
    echo Please install Docker Desktop with Compose support.
    pause
    exit /b 1
)

echo Docker and Docker Compose are installed.
echo.

echo [2/3] Starting services with Docker Compose...
docker-compose up -d --build

echo.
echo [3/3] Waiting for services to be ready...
timeout /t 5 /nobreak >nul

echo.
echo ==========================================
echo   Application is starting...
echo ==========================================
echo.
echo Database:    http://localhost:5432 (postgres / postgres)
echo Website:     http://localhost:8000
echo.
echo First time setup:
echo   1. Visit http://localhost:8000/register.php to create an account
echo   2. Run: docker-compose exec db psql -U postgres -d nupsg_church -c "UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';"
echo   3. Log in with your new admin account
echo.
echo Useful commands:
echo   docker-compose logs -f          - View logs
echo   docker-compose stop             - Stop services
echo   docker-compose down             - Stop and remove containers
echo   docker-compose down -v          - Stop and remove containers + volumes (WARNING: deletes data)
echo.
echo Press any key to open the website...
pause >nul
start http://localhost:8000
