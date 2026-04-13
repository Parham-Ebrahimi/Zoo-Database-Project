# Zoo-Database-Project
# Greenwood Wildlife Zoo — Management System
### COSC 3380 — Team 9

## Live Website
URL: https://team9zooproject-ctfjc8b7fzhcggeh.eastus2-01.azurewebsites.net/webapp/login.html

## Admin Access
- **Admin Login Page:** /login.html
- **Username:** admin
- **Password:** admin123

## Customer Access
- **Sign in (staff or member):** /sign-in.html
- **Sign Up Page:** /signup.html

## Repository layout
- All PHP and static assets for the site live in **`/webapp`** at the repository root (there is no second copy under `Zoo-Database-Project/webapp`).

## Database MySQL
- **Host:** team9zoodb.mysql.database.azure.com
- **Database:** zoo_management
- **Admin:** zooadmin

## Features
### User Authentication
- Admin/Employee login via systemuser table
- Customer login and registration via customers table
- Session-based authentication with role separation

### Data Entry (Admin)
- Add, edit, and delete Animals
- Add, edit, and delete Employees
- Ticket orders are created by customers via **buy-tickets.php** (orders / order_tickets tables)

### Reports
- Animals report with enclosure info
- Employees report
- Tickets report
- Customer-facing animals and tickets reports

### Database Triggers
- Prevent salary = 0 on INSERT
- Prevent salary = 0 on UPDATE
- Prevent purchasing ticket for past date (assumes column visit_date exists)

## Tech Stack
- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP 8.2
- **Database:** MySQL 8.0 (Azure Database)
- **Hosting:** Azure App Service
- **CI/CD:** GitHub Actions
